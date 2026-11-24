<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_destination_is_normalized_and_missing_number_is_not_replaced_with_meta_id(): void
    {
        $church=\App\Models\Church::create(['name'=>'Church']);Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']));
        config(['whatsapp.phone_number' => '+1 (555) 173-3579', 'whatsapp.phone_number_id' => '1424245080775999']);
        $this->getJson('/api/auth/whatsapp-preferences')->assertOk()->assertJsonPath('destination_number', '+15551733579');
        config(['whatsapp.phone_number' => null]);
        $this->getJson('/api/auth/whatsapp-preferences')->assertOk()->assertJsonPath('destination_number', null);
        config(['whatsapp.phone_number' => 'not-a-phone']);
        $this->getJson('/api/auth/whatsapp-preferences')->assertOk()->assertJsonPath('destination_number', null);
    }

    public function test_opt_in_is_off_by_default_and_only_changes_authenticated_account(): void
    {
        $this->getJson('/api/auth/whatsapp-preferences')->assertUnauthorized();
        $church=\App\Models\Church::create(['name'=>'Church']);$user = User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']);
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
