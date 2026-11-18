<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppMessage;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\Objective;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\Access;
use App\Services\WhatsApp\Conversation;
use App\Services\WhatsApp\Interpreter;
use App\Services\WhatsApp\Transport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppPilotTest extends TestCase
{
    use RefreshDatabase;

    private function setupPilot(): WhatsAppAccount
    {
        Http::preventStrayRequests();
        Queue::fake();
        $church = Church::create(['name' => 'Pilot', 'slug' => 'pilot']);
        $department = Department::create(['church_id' => $church->id, 'name' => 'Music']);
        $user = User::factory()->create(['church_id' => $church->id, 'department_id' => $department->id, 'role' => 'admin', 'whatsapp_assistant_enabled' => true]);
        config(['whatsapp.enabled' => true, 'whatsapp.church_id' => $church->id, 'whatsapp.app_secret' => 'secret', 'whatsapp.business_account_id' => '123', 'whatsapp.phone_number_id' => '456', 'whatsapp.graph_version' => 'v23.0', 'whatsapp.access_token' => 'fake']);

        return WhatsAppAccount::create(['user_id' => $user->id, 'sender' => '15555550101', 'sender_hash' => WhatsAppAccount::senderHash('15555550101'), 'last_received_at' => now()]);
    }

    private function incoming(string $body, string $id = 'msg1', string $sender = '15555550101', bool $valid = true)
    {
        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '123', 'changes' => [['field' => 'messages', 'value' => ['metadata' => ['phone_number_id' => '456'], 'messages' => [['id' => $id, 'from' => $sender, 'type' => 'text', 'text' => ['body' => $body], 'timestamp' => (string) now()->timestamp]]]]]]]]);

        return $this->call('POST', '/api/integrations/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.($valid ? hash_hmac('sha256', $payload, 'secret') : 'invalid')], $payload);
    }

    public function test_signed_inbox_deduplication_unknown_senders_and_global_switch(): void
    {
        $a = $this->setupPilot();
        $this->incoming('hello', valid: false)->assertForbidden();
        $this->incoming('hello', sender: '15555550999')->assertOk();
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->incoming('hello')->assertOk();
        $this->incoming('hello')->assertOk();
        $this->assertDatabaseCount('whatsapp_messages', 1);
        Queue::assertPushed(ProcessWhatsAppMessage::class, 1);
        $this->assertNotSame('hello', \DB::table('whatsapp_messages')->value('body'));
        config(['whatsapp.enabled' => false]);
        $this->incoming('another', 'msg2')->assertOk();
        $this->assertDatabaseCount('whatsapp_messages', 1);
        Http::assertNothingSent();
    }

    public function test_linking_is_expiring_single_use_and_stop_invalidates_pending_work(): void
    {
        $a = $this->setupPilot();
        $a->update(['sender' => null, 'sender_hash' => null]);
        Sanctum::actingAs($a->user);
        $command = $this->postJson('/api/auth/whatsapp-link')->assertOk()->json('command');
        $this->incoming($command)->assertOk();
        $this->assertNotNull($a->fresh()->sender_hash);
        $this->assertNull($a->fresh()->link_hash);
        $this->incoming($command, 'msg2', '15555550202')->assertOk();
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->incoming('BAJA', 'msg3')->assertOk();
        $this->assertFalse($a->user->fresh()->whatsapp_assistant_enabled);
        $this->assertSame('ignored', WhatsAppMessage::first()->status);
        Http::assertNothingSent();
    }

    private function draft(WhatsAppAccount $a): array
    {
        $o = Objective::create(['department_id' => $a->user->department_id, 'name' => 'Worship', 'description' => 'Music', 'evaluation_metrics' => 'Attendance']);

        return ['intent' => 'event', 'reply' => 'Revisa los datos.', 'title' => 'Practice', 'description' => 'Choir', 'location' => 'Church', 'objective_id' => $o->id, 'start_at' => '2030-01-01T10:00:00-05:00', 'end_at' => '2030-01-01T11:00:00-05:00'];
    }

    public function test_event_hold_extend_confirm_conflict_and_resubmit_same_event(): void
    {
        $a = $this->setupPilot();
        $draft = $this->draft($a);
        $this->mock(Interpreter::class)->shouldReceive('parse')->andReturn($draft);
        $conversation = app(Conversation::class);
        $conversation->reply($a, 'Create practice');
        $a->refresh();
        $id = $a->state['hold_id'];
        $this->assertSame('hold', Event::find($id)->status);
        $this->travel(2)->minutes();
        $conversation->reply($a, 'EXTENDER');
        $this->assertEquals(now()->addMinutes(5)->timestamp, Event::find($id)->expires_at->timestamp);
        $conversation->reply($a, 'CONFIRMAR');
        $a->refresh();
        $this->assertSame('pending', Event::find($id)->review_status);
        $this->assertSame('locked', Event::find($id)->status);
        $this->assertFalse(Event::find($id)->publish_to_feed);
        Event::find($id)->update(['review_status' => 'changes_requested']);
        $conversation->reply($a, "EDITAR $id");
        $a->refresh();
        $conversation->reply($a, 'Change details');
        $a->refresh();
        $conversation->reply($a, 'CONFIRMAR');
        $this->assertDatabaseCount('events', 1);
        $this->assertSame('resubmitted', Event::find($id)->review_status);
        $a->refresh();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $conversation->reply($a, 'Conflicting practice');
    }

    public function test_accepted_event_cannot_be_edited_and_opt_out_blocks_ai_and_transport(): void
    {
        $a = $this->setupPilot();
        $a->user->forceFill(['whatsapp_assistant_enabled' => false])->save();
        $this->assertNull(app(Conversation::class)->reply($a, 'Create event'));
        $this->assertFalse(app(Transport::class)->send($a, 'hello'));
        Http::assertNothingSent();
    }

    public function test_usage_limits_are_hard_and_delivery_is_not_retried(): void
    {
        $a = $this->setupPilot();
        config(['whatsapp.monthly_replies_limit' => 1]);
        Http::fake(['graph.facebook.com/*' => Http::response([], 500)]);
        $this->assertFalse(app(Transport::class)->send($a, 'one'));
        $this->assertFalse(app(Transport::class)->send($a, 'two'));
        Http::assertSentCount(1);
    }

    public function test_queued_job_rechecks_permissions_and_does_not_reprocess(): void
    {
        $a = $this->setupPilot();
        $m = WhatsAppMessage::create(['provider_id' => 'x', 'account_id' => $a->id, 'body' => 'AYUDA']);
        $a->user->forceFill(['whatsapp_assistant_enabled' => false])->save();
        $job = new ProcessWhatsAppMessage($m->id, $a->id);
        $job->handle();
        $job->handle();
        $this->assertSame('ignored', $m->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_expired_hold_cannot_be_confirmed(): void
    {
        $a = $this->setupPilot();
        $draft = $this->draft($a);
        $this->mock(Interpreter::class)->shouldReceive('parse')->andReturn($draft);
        $c = app(Conversation::class);
        $c->reply($a, 'create');
        $a->refresh();
        $this->travel(6)->minutes();
        try {
            $c->reply($a, 'CONFIRMAR');
            $this->fail('Expired hold accepted');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
        }
        $this->assertSame('hold', Event::first()->status);
        $this->assertNull(Event::first()->accepted_at);
    }

    public function test_current_permissions_and_accepted_event_are_enforced(): void
    {
        $a = $this->setupPilot();
        $draft = $this->draft($a);
        $this->mock(Interpreter::class)->shouldReceive('parse')->andReturn($draft);
        $c = app(Conversation::class);
        $c->reply($a, 'create');
        $a->refresh();
        $c->reply($a, 'CONFIRMAR');
        $a->refresh();
        $event = Event::first();
        $event->update(['review_status' => 'approved']);
        try {
            $c->reply($a, 'EDITAR '.$event->id);
            $this->fail('Approved edit allowed');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $a->user->update(['role' => 'secretary']);
        try {
            $c->reply($a, 'create another');
            $this->fail('Clerk created department event');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
        }
        $this->assertDatabaseCount('events', 1);
    }

    public function test_visitor_counts_are_scoped_and_links_require_login(): void
    {
        $a = $this->setupPilot();
        $a->user->update(['role' => 'pastor', 'department_id' => null]);
        \App\Models\ChurchContact::create(['church_id' => $a->user->church_id, 'name' => 'Private Visitor', 'is_sda' => false, 'sms_consent' => false, 'email_consent' => false]);
        $other = Church::create(['name' => 'Other', 'slug' => 'other']);
        \App\Models\ChurchContact::create(['church_id' => $other->id, 'name' => 'Other Visitor', 'is_sda' => false, 'sms_consent' => false, 'email_consent' => false]);
        $this->mock(Interpreter::class)->shouldReceive('parse')->andReturn(['intent' => 'visitors', 'from' => now()->toDateString(), 'to' => now()->toDateString()]);
        $reply = app(Conversation::class)->reply($a, 'visitors today');
        $this->assertStringStartsWith('1 registros', $reply);
        $this->assertStringContainsString('/greeting/contacts?from=', $reply);
        $this->assertStringNotContainsString('Private Visitor', $reply);
        $this->getJson('/api/church-contacts')->assertUnauthorized();
    }

    public function test_interpreter_uses_bounded_nonstored_structured_output(): void
    {
        $a = $this->setupPilot();
        config(['assistant.openai_api_key' => 'test', 'assistant.openai_model' => 'test-model']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{"intent":"help","reply":"Hola"}']]]]])]);
        $result = app(Interpreter::class)->parse($a, 'hello', []);
        $this->assertSame('help', $result['intent']);
        Http::assertSent(fn ($r) => $r['store'] === false && $r['max_output_tokens'] === 700 && $r['text']['format']['strict'] === true);
        config(['whatsapp.monthly_ai_limit' => 1]);
        try {
            app(Interpreter::class)->parse($a, 'again', []);
            $this->fail('Quota bypassed');
        } catch (\RuntimeException) {
        }
        Http::assertSentCount(1);
    }

    public function test_expired_link_code_and_relink_do_not_restore_old_work(): void
    {
        $a = $this->setupPilot();
        Sanctum::actingAs($a->user);
        $this->deleteJson('/api/auth/whatsapp-link')->assertOk();
        $command = $this->postJson('/api/auth/whatsapp-link')->assertOk()->json('command');
        $this->travel(11)->minutes();
        $this->incoming($command)->assertOk();
        $this->assertNull($a->fresh()->sender_hash);
        $command = $this->postJson('/api/auth/whatsapp-link')->assertOk()->json('command');
        $this->incoming($command, 'fresh')->assertOk();
        $a->refresh();
        $this->patchJson('/api/auth/whatsapp-preferences', ['enabled' => false])->assertOk();
        $this->patchJson('/api/auth/whatsapp-preferences', ['enabled' => true])->assertOk();
        $this->assertFalse(app(Access::class)->allowed($a));
        $this->assertSame('ignored', WhatsAppMessage::first()->status);
    }

    public function test_hold_warning_and_webhook_audit_do_not_leak_text(): void
    {
        $a = $this->setupPilot();
        $draft = $this->draft($a);
        $this->mock(Interpreter::class)->shouldReceive('parse')->andReturn($draft);
        app(Conversation::class)->reply($a, 'create');
        $a->refresh();
        $event = Event::first();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'ok']]])]);
        $warning = new \App\Jobs\WhatsAppHoldWarning($a->id, $event->id, $event->expires_at->toIso8601String());
        $warning->handle();
        Http::assertSentCount(1);
        $this->travel(6)->minutes();
        $warning->handle();
        Http::assertSentCount(1);
        $this->incoming('private-body', 'private');
        $this->assertStringNotContainsString('private-body', json_encode(\App\Models\AuditLog::all()));
    }

    public function test_worker_replies_once_and_failed_delivery_does_not_replay(): void
    {
        $a = $this->setupPilot();
        Http::fake(['graph.facebook.com/*' => Http::response([], 500)]);
        $message = WhatsAppMessage::create(['provider_id' => 'once', 'account_id' => $a->id, 'body' => 'AYUDA']);
        $job = new ProcessWhatsAppMessage($message->id, $a->id);
        $job->handle();
        $job->handle();
        Http::assertSentCount(1);
        $this->assertSame('reply_failed', $message->fresh()->status);
        $this->assertNull($message->fresh()->body);
    }

    public function test_warning_is_suppressed_after_opt_out(): void
    {
        $a = $this->setupPilot();
        $this->mock(Interpreter::class)->shouldReceive('parse')->andReturn($this->draft($a));
        app(Conversation::class)->reply($a, 'create');
        $event = Event::first();
        $a->user->forceFill(['whatsapp_assistant_enabled' => false])->save();
        (new \App\Jobs\WhatsAppHoldWarning($a->id, $event->id, $event->expires_at->toIso8601String()))->handle();
        Http::assertNothingSent();
    }
}
