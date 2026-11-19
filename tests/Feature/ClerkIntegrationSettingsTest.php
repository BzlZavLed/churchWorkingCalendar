<?php
namespace Tests\Feature;
use App\Models\{Church,User};
use App\Services\ChurchIntegrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClerkIntegrationSettingsTest extends TestCase
{
 use RefreshDatabase;
 public function test_unknown_api_route_does_not_return_spa_html(): void {
  $this->get('/api/nonexistent-integration-route', ['Accept'=>'application/json'])->assertNotFound();
 }

 public function test_clerk_reads_assigned_limits_and_controls_only_own_switches(): void {
  $church=Church::create(['name'=>'Own']);$other=Church::create(['name'=>'Other']);
  $church->forceFill(['whatsapp_ai_limit'=>12])->save();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $url='/api/secretary/integrations';
  $this->getJson($url)->assertOk()->assertJsonPath('limits.whatsapp_ai_limit',12)->assertJsonMissingPath('usage.ai.global_used');
  $this->putJson($url,['whatsapp_enabled'=>false,'ai_enabled'=>false])->assertOk()->assertJsonPath('whatsapp_enabled',false);
  $service=app(ChurchIntegrations::class);
  $this->assertFalse($service->whatsapp($church->id));$this->assertFalse($service->ai($church->id));
  $this->assertTrue($service->whatsapp($other->id));
  $this->putJson($url,['whatsapp_enabled'=>true,'ai_enabled'=>true,'whatsapp_ai_limit'=>100])->assertUnprocessable();
  $this->putJson($url,['whatsapp_enabled'=>true,'ai_enabled'=>true,'church_id'=>$other->id])->assertUnprocessable();
  $church->forceFill(['whatsapp_enabled'=>false,'ai_enabled'=>false])->save();
  $this->putJson($url,['whatsapp_enabled'=>true,'ai_enabled'=>true])->assertOk()->assertJsonPath('admin_whatsapp_enabled',false);
  $this->assertFalse($service->whatsapp($church->id));$this->assertFalse($service->ai($church->id));
  $this->getJson('/api/superadmin/churches/'.$church->id.'/integrations/providers?month=2026-11')->assertForbidden();
 }
 public function test_department_cannot_read_or_change_church_settings(): void {
  $church=Church::create(['name'=>'Own']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));
  $this->getJson('/api/secretary/integrations')->assertForbidden();
  $this->putJson('/api/secretary/integrations',['whatsapp_enabled'=>false,'ai_enabled'=>false])->assertForbidden();
 }
}
