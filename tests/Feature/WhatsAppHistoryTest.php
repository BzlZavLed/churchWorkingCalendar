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
