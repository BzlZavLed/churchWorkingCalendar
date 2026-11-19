<?php
namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppMessage;
use App\Models\{Church,User,WhatsAppAccount,WhatsAppMessage};
use App\Services\WhatsApp\{AssistantFailure,Interpreter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Cache};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppDiagnosticsTest extends TestCase
{
 use RefreshDatabase;
 private function account(Church $church, string $role='secretary'): WhatsAppAccount {
  $u=User::factory()->create(['church_id'=>$church->id,'role'=>$role,'whatsapp_assistant_enabled'=>true]);
  return WhatsAppAccount::create(['user_id'=>$u->id,'sender'=>'15555550101','sender_hash'=>WhatsAppAccount::senderHash((string)$u->id),'last_received_at'=>now()]);
 }
 private function configure(Church $church): void {
  Http::preventStrayRequests();Cache::flush();
  config(['whatsapp.enabled'=>true,'whatsapp.church_id'=>$church->id,'whatsapp.phone_number_id'=>'123','whatsapp.business_account_id'=>'456','whatsapp.graph_version'=>'v25.0','whatsapp.access_token'=>'private-meta-key','whatsapp.app_secret'=>'private-secret','whatsapp.verify_token'=>'private-verify','assistant.openai_api_key'=>'private-openai-key','assistant.openai_model'=>'test-model']);
 }
 public function test_diagnostics_are_church_scoped_and_do_not_make_provider_calls(): void {
  $church=Church::create(['name'=>'Own']);$other=Church::create(['name'=>'Other']);
  $this->configure($church);$a=$this->account($church);$foreign=$this->account($other);
  $own=WhatsAppMessage::create(['provider_id'=>'own','account_id'=>$a->id,'status'=>'reply_failed','body'=>'private-content','reply_status'=>'meta_error_190']);
  WhatsAppMessage::create(['provider_id'=>'foreign','account_id'=>$foreign->id,'status'=>'failed','error_code'=>'internal_error']);
  WhatsAppMessage::create(['provider_id'=>'pending','account_id'=>$a->id,'status'=>'pending','created_at'=>now()->subMinutes(3)]);
  Sanctum::actingAs($a->user);
  $response=$this->getJson('/api/secretary/whatsapp-diagnostics?church_id='.$other->id)->assertOk()->assertJsonCount(2,'messages')->assertJsonPath('configuration.WHATSAPP_ACCESS_TOKEN',true)->assertJsonPath('pending_over_two_minutes',1);
  $this->assertStringNotContainsString('private-', $response->getContent());
  Http::assertNothingSent();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));
  $this->getJson('/api/secretary/whatsapp-diagnostics')->assertForbidden();
  $this->postJson('/api/secretary/whatsapp-diagnostics/check')->assertForbidden();
 }
 public function test_provider_check_uses_cached_read_only_calls_and_hides_raw_errors(): void {
  $church=Church::create(['name'=>'Own']);$this->configure($church);$a=$this->account($church);
  Sanctum::actingAs($a->user);
  Http::fake([
   'graph.facebook.com/*'=>Http::response(['error'=>['code'=>190,'message'=>'private-provider-body']],401),
   'api.openai.com/*'=>Http::response(['id'=>'test-model']),
  ]);
  $url='/api/secretary/whatsapp-diagnostics/check';
  $r=$this->postJson($url)->assertOk()->assertJsonPath('meta.code','meta_error_190')->assertJsonPath('openai.status','reachable');
  $this->assertStringNotContainsString('private-', $r->getContent());
  $this->postJson($url)->assertOk();Http::assertSentCount(2);
  Http::assertNotSent(fn($r)=>$r->method()!=='GET');
 }
 public function test_worker_records_processing_and_delivery_errors_separately(): void {
  $church=Church::create(['name'=>'Own']);$this->configure($church);$a=$this->account($church);
  $m=WhatsAppMessage::create(['provider_id'=>'request','account_id'=>$a->id,'status'=>'pending','body'=>'Describe an event']);
  $this->mock(Interpreter::class,fn($mock)=>$mock->shouldReceive('parse')->once()->andThrow(new AssistantFailure('openai_insufficient_quota')));
  Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['code'=>190,'message'=>'secret-provider-body']],401)]);
  (new ProcessWhatsAppMessage($m->id,$a->id))->handle();
  $m->refresh();
  $this->assertSame('failed',$m->status);
  $this->assertSame('openai_insufficient_quota',$m->error_code);
  $this->assertSame('meta_error_190',$m->reply_status);
  $this->assertNull($m->body);
 }
 public function test_successful_processing_with_failed_reply_retains_meta_error(): void {
  $church=Church::create(['name'=>'Own']);$this->configure($church);$a=$this->account($church);
  $m=WhatsAppMessage::create(['provider_id'=>'help','account_id'=>$a->id,'status'=>'pending','body'=>'AYUDA']);
  Http::fake(['graph.facebook.com/*'=>Http::response(['error'=>['code'=>131030]],400)]);
  (new ProcessWhatsAppMessage($m->id,$a->id))->handle();
  $this->assertSame('reply_failed',$m->fresh()->status);
  $this->assertSame('meta_error_131030',$m->fresh()->reply_status);
 }
}
