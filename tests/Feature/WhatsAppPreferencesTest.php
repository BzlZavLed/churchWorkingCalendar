<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_in_is_off_by_default_and_only_changes_authenticated_account(): void
    {
        $this->getJson('/api/auth/whatsapp-preferences')->assertUnauthorized();
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/auth/whatsapp-preferences')->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('ready', false);
        $this->patchJson('/api/auth/whatsapp-preferences', ['enabled' => true, 'user_id' => $other->id])->assertOk()->assertJsonPath('enabled', true);
        $this->assertFalse((bool) $other->fresh()->whatsapp_assistant_enabled);
        $this->patchJson('/api/auth/whatsapp-preferences', ['enabled' => false])->assertOk()->assertJsonPath('enabled', false);
        $this->patchJson('/api/auth/whatsapp-preferences', ['enabled' => 'yes'])->assertUnprocessable();
    }

    public function test_linking_explains_superadmin_and_missing_pilot_configuration(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.church_id' => null]);
        $user = User::factory()->create(['role' => 'superadmin', 'church_id' => null, 'whatsapp_assistant_enabled' => true]);
        Sanctum::actingAs($user);
        $this->getJson('/api/auth/whatsapp-preferences')->assertJsonPath('link_block_reason', 'account_without_church');
        $this->postJson('/api/auth/whatsapp-link')->assertForbidden()->assertJsonPath('reason', 'account_without_church');
        $church = \App\Models\Church::create(['name' => 'Pilot', 'slug' => 'pilot']);
        $user->update(['role' => 'pastor', 'church_id' => $church->id]);
        $this->postJson('/api/auth/whatsapp-link')->assertForbidden()->assertJsonPath('reason', 'pilot_not_configured');
        config(['whatsapp.church_id' => $church->id]);
        $this->postJson('/api/auth/whatsapp-link')->assertOk()->assertJsonStructure(['command', 'expires_in']);
    }
}
