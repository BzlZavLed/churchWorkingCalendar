<?php
namespace Tests\Feature;
use App\Models\{Church,User,WhatsAppAccount,WhatsAppMessage};
use App\Services\WhatsApp\{Access,Inbox};
use App\Services\ChurchIntegrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Queue,Http};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ChurchIntegrationTest extends TestCase {
 use RefreshDatabase;
 private function setupChurch(): array {
  Queue::fake();Http::preventStrayRequests();
  $church=Church::create(['name'=>'Pilot']);config(['whatsapp.enabled'=>true,'whatsapp.church_id'=>$church->id]);
  $admin=User::factory()->create(['role'=>'superadmin']);Sanctum::actingAs($admin);
  return [$church,$admin];
 }
 private function userPayload(string $email='member@example.test'): array {return ['name'=>'Member','email'=>$email,'password'=>'test-password','role'=>'member','whatsapp_phone'=>'+15555550101','whatsapp_assistant_enabled'=>true];}
 public function test_provisioned_number_links_on_first_message_and_reassignment_revokes_old_access(): void {
  [$church]=$this->setupChurch();
  $url='/api/superadmin/churches/'.$church->id.'/users';
  $id=$this->postJson($url,$this->userPayload())->assertCreated()->assertJsonPath('whatsapp_linked',false)->json('id');
  $a=WhatsAppAccount::where('user_id',$id)->firstOrFail();$this->assertNull($a->sender_hash);
  $this->assertNotSame('15555550101',DB::table('whatsapp_accounts')->value('registered_sender'));
  $this->getJson($url)->assertOk()->assertJsonPath('0.whatsapp_phone','+15555550101');
  app(Inbox::class)->accept('first','15555550101','Hola',now()->timestamp);
  $a->refresh();$this->assertTrue(app(Access::class)->allowed($a));$this->assertSame('AYUDA',WhatsAppMessage::first()->body);
  $this->postJson($url,$this->userPayload('duplicate@example.test'))->assertUnprocessable();
  $this->assertDatabaseMissing('users',['email'=>'duplicate@example.test']);
  $this->putJson($url.'/'.$id,['whatsapp_phone'=>'+15555550202'])->assertOk()->assertJsonPath('whatsapp_linked',false);
  $this->assertFalse(app(Access::class)->allowed($a));$this->assertSame('ignored',WhatsAppMessage::first()->status);
  app(Inbox::class)->accept('old','15555550101','Hola',now()->timestamp);$this->assertDatabaseCount('whatsapp_messages',1);
  app(Inbox::class)->accept('new','15555550202','Hola',now()->timestamp);$this->assertDatabaseCount('whatsapp_messages',2);
  $this->putJson($url.'/'.$id,['whatsapp_assistant_enabled'=>false])->assertOk();
  $this->assertFalse(app(Access::class)->allowed($a->fresh()));
  Http::assertNothingSent();
 }
 public function test_settings_permissions_limits_and_church_switches(): void {
  [$church,$admin]=$this->setupChurch();$url='/api/superadmin/churches/'.$church->id.'/integrations';
  $initial=$this->getJson($url)->assertOk()->json();
  $settings=$initial['limits']+['whatsapp_enabled'=>true,'ai_enabled'=>false];$settings['whatsapp_replies_limit']=1;
  $this->putJson($url,$settings)->assertOk()->assertJsonPath('ai_enabled',false);
  $u=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary','whatsapp_assistant_enabled'=>true]);
  $a=WhatsAppAccount::create(['user_id'=>$u->id,'sender'=>'15555550303','sender_hash'=>WhatsAppAccount::senderHash('15555550303')]);
  $this->assertFalse(app(Access::class)->consume($a,'ai'));
  $this->assertTrue(app(Access::class)->consume($a,'replies'));$this->assertFalse(app(Access::class)->consume($a,'replies'));
  $this->getJson($url)->assertJsonPath('usage.replies.used',1);
  app(ChurchIntegrations::class)->record($church->id,'whatsapp',['input_tokens'=>15,'output_tokens'=>7]);
  $this->getJson($url)->assertJsonPath('tokens.0.input_tokens',15);
  $settings['whatsapp_enabled']=false;$this->putJson($url,$settings)->assertOk();$this->assertFalse(app(Access::class)->allowed($a));
  Sanctum::actingAs($u);$this->getJson($url)->assertForbidden();$this->putJson($url,$settings)->assertForbidden();
  $this->postJson('/api/auth/whatsapp-link')->assertForbidden()->assertJsonPath('reason','church_disabled');
  Http::assertNothingSent();
 }
 public function test_clerk_provisions_only_own_church_and_disabled_pending_number_does_not_link(): void {
  [$church]=$this->setupChurch();$other=Church::create(['name'=>'Other']);
  $foreign=User::factory()->create(['church_id'=>$other->id]);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $payload=$this->userPayload();$payload['whatsapp_assistant_enabled']=false;
  $id=$this->postJson('/api/secretary/users',$payload)->assertCreated()->json('id');
  $this->putJson('/api/secretary/users/'.$foreign->id,['whatsapp_phone'=>'+15555550404'])->assertNotFound();
  app(Inbox::class)->accept('blocked','15555550101','Hola',now()->timestamp);
  $this->assertNull(WhatsAppAccount::where('user_id',$id)->first()->sender_hash);
  $this->putJson('/api/secretary/users/'.$id,['whatsapp_assistant_enabled'=>true])->assertOk();
  app(Inbox::class)->accept('allowed','15555550101','Hola',now()->timestamp);
  $this->assertNotNull(WhatsAppAccount::where('user_id',$id)->first()->sender_hash);
 }
}
