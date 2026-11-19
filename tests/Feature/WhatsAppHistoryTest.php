<?php
namespace Tests\Feature;

use App\Models\{Church, User, WhatsAppAccount, WhatsAppInteraction};
use App\Services\WhatsApp\{InteractionHistory, Transport};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_encrypted_scoped_and_filtered_by_local_day(): void
    {
        $this->travelTo(now()->setDate(2026,11,19)->setTime(21,0));
        $church = Church::create(['name'=>'Pilot']);
        $user = User::factory()->create(['church_id'=>$church->id]);
        $other = User::factory()->create(['church_id'=>$church->id]);
        $account = WhatsAppAccount::create(['user_id'=>$user->id]);
        $row = app(InteractionHistory::class)->record($account,'incoming','Private request','received');
        $row->update(['created_at'=>'2026-11-19 02:00:00']);
        WhatsAppInteraction::create(['user_id'=>$other->id,'church_id'=>$church->id,'direction'=>'incoming','content'=>'Other user secret','status'=>'received']);
        $this->assertStringNotContainsString('Private request', DB::table('whatsapp_interactions')->where('id',$row->id)->value('content'));
        $this->getJson('/api/auth/whatsapp-history?date=2026-11-18&timezone=America/New_York')->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->getJson('/api/auth/whatsapp-history?date=2026-11-18&timezone=America/New_York')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.content','Private request');
        $this->getJson('/api/auth/whatsapp-history?date=2026-11-19&timezone=America/New_York&user_id='.$other->id)->assertOk()->assertJsonPath('total',0);
        $this->getJson('/api/auth/whatsapp-history?date=2026-11-19&timezone=invalid')->assertUnprocessable();
        $row->update(['created_at'=>now()->subDays(31)]);
        $this->artisan('whatsapp:prune')->assertSuccessful();
        $this->assertDatabaseMissing('whatsapp_interactions',['id'=>$row->id]);
    }

    public function test_clerk_sees_own_church_departments_but_department_user_cannot(): void
    {
        $church = Church::create(['name'=>'Own']);
        $otherChurch = Church::create(['name'=>'Other']);
        $department = \App\Models\Department::create(['name'=>'Music','church_id'=>$church->id]);
        $clerk = User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);
        $member = User::factory()->create(['church_id'=>$church->id,'department_id'=>$department->id,'role'=>'admin']);
        $outsider = User::factory()->create(['church_id'=>$otherChurch->id]);
        foreach([$clerk,$member,$outsider] as $user) {
            WhatsAppInteraction::create(['user_id'=>$user->id,'church_id'=>$user->church_id,'direction'=>'incoming','content'=>'request','status'=>'received']);
        }
        $url='/api/secretary/whatsapp-history?date='.now()->format('Y-m-d').'&timezone=UTC';
        Sanctum::actingAs($clerk);
        $this->getJson($url)->assertOk()->assertJsonPath('total',2)->assertJsonPath('departments.0.name','Music');
        $this->getJson($url.'&department_id='.$department->id)->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.user_name',$member->name);
        $this->getJson($url.'&church_id='.$otherChurch->id)->assertOk()->assertJsonPath('total',2);
        Sanctum::actingAs($member);
        $this->getJson($url)->assertForbidden();
        $this->getJson(str_replace('/secretary/','/auth/',$url))->assertOk()->assertJsonPath('total',1);
    }

    public function test_meta_rejection_records_only_safe_numeric_code_and_reply_limit_is_visible(): void
    {
        Http::preventStrayRequests();
        $church=Church::create(['name'=>'Pilot']);
        $user=User::factory()->create(['church_id'=>$church->id,'whatsapp_assistant_enabled'=>true]);
        $account=WhatsAppAccount::create(['user_id'=>$user->id,'sender'=>'15555550101','sender_hash'=>WhatsAppAccount::senderHash('15555550101'),'last_received_at'=>now()]);
        config(['whatsapp.enabled'=>true,'whatsapp.church_id'=>$church->id,'whatsapp.graph_version'=>'v25.0','whatsapp.phone_number_id'=>'123','whatsapp.access_token'=>'fake']);
        Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['code'=>131030,'message'=>'private provider details']],400)]);
        $this->assertFalse(app(Transport::class)->send($account,'Reply'));
        $this->assertSame('meta_error_131030',WhatsAppInteraction::latest('id')->first()->status);
        config(['whatsapp.monthly_replies_limit'=>0]);
        $this->assertFalse(app(Transport::class)->send($account,'Another reply'));
        $this->assertSame('reply_limit',WhatsAppInteraction::latest('id')->first()->status);
        Http::assertSentCount(1);
    }

    public function test_link_codes_are_redacted_and_outbound_attempt_is_recorded(): void
    {
        Http::preventStrayRequests();
        $church = Church::create(['name'=>'Pilot']);
        $user = User::factory()->create(['church_id'=>$church->id,'whatsapp_assistant_enabled'=>true]);
        $account=WhatsAppAccount::create(['user_id'=>$user->id,'sender'=>'15555550101','sender_hash'=>WhatsAppAccount::senderHash('15555550101'),'last_received_at'=>now()]);
        $row=app(InteractionHistory::class)->record($account,'incoming','VINCULAR '.str_repeat('a',32),'received');
        $this->assertSame('VINCULAR [código oculto]',$row->content);
        config(['whatsapp.enabled'=>true,'whatsapp.church_id'=>$church->id,'whatsapp.graph_version'=>'v25.0','whatsapp.phone_number_id'=>'123','whatsapp.access_token'=>'fake']);
        Http::fake(['graph.facebook.com/*'=>Http::response(['messages'=>[['id'=>'outgoing']]])]);
        $this->assertTrue(app(Transport::class)->send($account,'Test reply'));
        $reply=WhatsAppInteraction::where('direction','outgoing')->firstOrFail();
        $this->assertSame('Test reply',$reply->content);
        $this->assertSame('sent',$reply->status);
    }
}
