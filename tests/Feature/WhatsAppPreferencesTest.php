<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class WhatsAppPreferencesTest extends TestCase {
 use RefreshDatabase;
 public function test_opt_in_is_off_by_default_and_only_changes_authenticated_account(): void {
  $this->getJson('/api/auth/whatsapp-preferences')->assertUnauthorized();
  $user=User::factory()->create();$other=User::factory()->create();Sanctum::actingAs($user);
  $this->getJson('/api/auth/whatsapp-preferences')->assertOk()->assertJsonPath('enabled',false)->assertJsonPath('ready',false);
  $this->patchJson('/api/auth/whatsapp-preferences',['enabled'=>true,'user_id'=>$other->id])->assertOk()->assertJsonPath('enabled',true);
  $this->assertFalse((bool)$other->fresh()->whatsapp_assistant_enabled);
  $this->patchJson('/api/auth/whatsapp-preferences',['enabled'=>false])->assertOk()->assertJsonPath('enabled',false);
  $this->patchJson('/api/auth/whatsapp-preferences',['enabled'=>'yes'])->assertUnprocessable();
 }
}
