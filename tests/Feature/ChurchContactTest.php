<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchContact;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChurchContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_phone_registration_is_scoped_and_does_not_expose_contact_tokens(): void
    {
        $church = Church::create(['name' => 'Welcome church']);
        $other = Church::create(['name' => 'Other']);
        $this->getJson("/api/public/churches/{$church->id}/visit")->assertOk()->assertJsonPath('name', 'Welcome church')->assertJsonPath('form.version', 0);
        $payload = ['name' => 'Phone visitor', 'is_sda' => false, 'sms_consent' => false, 'email_consent' => false,
            'church_id' => $other->id, 'created_by' => 999, 'department_id' => 999];
        $this->postJson("/api/public/churches/{$church->id}/visit", $payload)
            ->assertCreated()->assertExactJson(['message' => 'Visitor registered.']);
        $this->assertDatabaseHas('church_contacts', ['name' => 'Phone visitor', 'church_id' => $church->id,
            'created_by' => null, 'department_id' => null, 'sms_consent' => false]);
        $payload['sms_consent'] = true;
        $this->postJson("/api/public/churches/{$church->id}/visit", $payload)->assertUnprocessable();
        $this->getJson('/api/public/churches/999999/visit')->assertNotFound();
    }

    public function test_clerk_can_restore_each_channel_with_confirmed_consent(): void
    {
        $church = Church::create(['name' => 'Main']);
        $contact = ChurchContact::create(['church_id' => $church->id, 'name' => 'Visitor',
            'phone' => '555-1010', 'email' => 'visitor@example.test', 'sms_consent' => false, 'email_consent' => false]);
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $church->id]));
        $url = "/api/church-contacts/{$contact->id}/consent";
        $this->patchJson($url, ['channel' => 'sms'])->assertUnprocessable();
        $this->patchJson($url, ['channel' => 'sms', 'consent_confirmed' => true])
            ->assertOk()->assertJsonPath('sms_consent', true)->assertJsonPath('email_consent', false);
        $this->assertNotNull($contact->fresh()->sms_consented_at);
        $this->patchJson($url, ['channel' => 'email', 'consent_confirmed' => true])
            ->assertOk()->assertJsonPath('email_consent', true);
        $this->assertNotNull($contact->fresh()->email_consented_at);
        $this->postJson("/api/public/consent/{$contact->consent_token}/revoke",
            ['withdraw_sms' => true, 'withdraw_email' => true])->assertOk()->assertJsonPath('contact.sms_consent', false);
    }

    public function test_restore_consent_checks_church_role_and_contact_details(): void
    {
        $church = Church::create(['name' => 'Main']);
        $other = Church::create(['name' => 'Other']);
        $contact = ChurchContact::create(['church_id' => $church->id, 'name' => 'Visitor']);
        $url = "/api/church-contacts/{$contact->id}/consent";
        $payload = ['channel' => 'sms', 'consent_confirmed' => true];
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $other->id]));
        $this->patchJson($url, $payload)->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'church_id' => $church->id]));
        $this->patchJson($url, $payload)->assertForbidden();
        $dept = Department::create(['church_id' => $church->id, 'name' => 'Welcome', 'is_greeting' => true]);
        Sanctum::actingAs(User::factory()->create(['role' => 'member', 'church_id' => $church->id, 'department_id' => $dept->id]));
        $this->patchJson($url, $payload)->assertUnprocessable();
        $contact->update(['phone' => '555-1010']);
        $this->patchJson($url, $payload)->assertOk()->assertJsonPath('sms_consent', true);
    }

    public function test_clerk_can_register_visitors_only_for_own_church(): void
    {
        $church = Church::create(['name' => 'Main']);
        $other = Church::create(['name' => 'Other']);
        $clerk = User::factory()->create(['role' => User::ROLE_SECRETARY, 'church_id' => $church->id, 'department_id' => null]);
        Sanctum::actingAs($clerk);
        $this->postJson('/api/church-contacts', [
            'name' => 'Visitor', 'church_id' => $other->id,
            'is_sda' => false, 'sms_consent' => false, 'email_consent' => false,
        ])->assertCreated()->assertJsonPath('church_id', $church->id)->assertJsonPath('created_by', $clerk->id);
        $this->assertDatabaseMissing('church_contacts', ['church_id' => $other->id]);
        $this->postJson('/api/church-contacts', [
            'name' => 'Visitor', 'is_sda' => false, 'sms_consent' => true, 'email_consent' => false,
        ])->assertUnprocessable();
    }

    public function test_greeting_department_user_can_create_contact(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        $department = Department::create([
            'church_id' => $church->id,
            'name' => 'Greeting Department',
            'is_greeting' => true,
        ]);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $department->id,
            'role' => User::ROLE_MEMBER,
        ]);

        Sanctum::actingAs($user->fresh()->load('department'));

        $response = $this->postJson('/api/church-contacts', [
            'name' => 'John Visitor',
            'phone' => '555-1010',
            'email' => 'john@example.com',
            'address' => 'Houston, TX',
            'is_sda' => false,
            'sms_consent' => true,
            'email_consent' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'John Visitor')
            ->assertJsonPath('is_sda', false)
            ->assertJsonPath('sms_consent', true)
            ->assertJsonPath('email_consent', true);

        $this->assertDatabaseHas('church_contacts', [
            'church_id' => $church->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'name' => 'John Visitor',
            'email' => 'john@example.com',
            'address' => 'Houston, TX',
            'sms_consent' => true,
            'email_consent' => true,
        ]);
    }

    public function test_non_greeting_member_cannot_create_contact(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        $department = Department::create([
            'church_id' => $church->id,
            'name' => 'Deacons',
            'is_greeting' => false,
        ]);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $department->id,
            'role' => User::ROLE_MEMBER,
        ]);

        Sanctum::actingAs($user->fresh()->load('department'));

        $response = $this->postJson('/api/church-contacts', [
            'name' => 'John Visitor',
            'is_sda' => false,
            'sms_consent' => false,
            'email_consent' => false,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('church_contacts', 0);
    }

    public function test_greeting_team_can_list_contacts_for_their_church(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        $department = Department::create([
            'church_id' => $church->id,
            'name' => 'Greeting Department',
            'is_greeting' => true,
        ]);

        $otherChurch = Church::create(['name' => 'Second Church']);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $department->id,
            'role' => User::ROLE_MEMBER,
        ]);

        ChurchContact::create([
            'church_id' => $church->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'name' => 'John Visitor',
            'address' => 'San Antonio, TX',
            'is_sda' => false,
            'sms_consent' => false,
            'email_consent' => false,
        ]);

        ChurchContact::create([
            'church_id' => $otherChurch->id,
            'name' => 'Outside Person',
            'is_sda' => false,
            'sms_consent' => false,
            'email_consent' => false,
        ]);

        Sanctum::actingAs($user->fresh()->load('department'));

        $response = $this->getJson('/api/church-contacts');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('John Visitor', $response->json()[0]['name']);
        $this->assertFalse($response->json()[0]['sms_consent']);
        $this->assertFalse($response->json()[0]['email_consent']);
    }

    public function test_contact_cannot_opt_into_sms_without_phone(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        $department = Department::create([
            'church_id' => $church->id,
            'name' => 'Greeting Department',
            'is_greeting' => true,
        ]);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $department->id,
            'role' => User::ROLE_MEMBER,
        ]);

        Sanctum::actingAs($user->fresh()->load('department'));

        $response = $this->postJson('/api/church-contacts', [
            'name' => 'John Visitor',
            'email' => 'john@example.com',
            'is_sda' => false,
            'sms_consent' => true,
            'email_consent' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sms_consent']);
    }

    public function test_contact_cannot_opt_into_email_without_email(): void
    {
        $church = Church::create(['name' => 'Main Church']);
        $department = Department::create([
            'church_id' => $church->id,
            'name' => 'Greeting Department',
            'is_greeting' => true,
        ]);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $department->id,
            'role' => User::ROLE_MEMBER,
        ]);

        Sanctum::actingAs($user->fresh()->load('department'));

        $response = $this->postJson('/api/church-contacts', [
            'name' => 'John Visitor',
            'phone' => '555-1010',
            'is_sda' => false,
            'sms_consent' => false,
            'email_consent' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email_consent']);
    }
}
