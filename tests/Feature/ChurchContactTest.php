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
