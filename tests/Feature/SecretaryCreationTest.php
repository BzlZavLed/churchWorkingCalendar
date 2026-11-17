<?php

namespace Tests\Feature;

use App\Models\{Church, Department, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecretaryCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_creates_departments_and_users_only_in_own_church(): void
    {
        $church = Church::create(['name' => 'Own']);
        $other = Church::create(['name' => 'Other']);
        $foreign = Department::create(['name' => 'Foreign', 'church_id' => $other->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $church->id]));
        $department = $this->postJson('/api/secretary/departments', [
            'name' => 'Music', 'church_id' => $other->id, 'color' => '#123456',
        ])->assertCreated()->assertJsonPath('church_id', $church->id)->json('id');
        $payload = ['name' => 'New user', 'email' => 'new@example.test', 'password' => 'Test-password-123',
            'role' => 'admin', 'department_id' => $department, 'church_id' => $other->id];
        $this->postJson('/api/secretary/users', $payload)->assertCreated()->assertJsonPath('church_id', $church->id)->assertJsonMissingPath('password');
        $this->assertTrue(Hash::check($payload['password'], User::where('email', $payload['email'])->firstOrFail()->password));
        $payload['email'] = 'another@example.test';
        $payload['department_id'] = $foreign->id;
        $this->postJson('/api/secretary/users', $payload)->assertUnprocessable();
        $payload['department_id'] = $department;
        $payload['role'] = 'superadmin';
        $this->postJson('/api/secretary/users', $payload)->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_department_roles_cannot_use_secretary_creation_routes(): void
    {
        foreach (['admin', 'member'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->postJson('/api/secretary/departments', ['name' => 'Forbidden'])->assertForbidden();
            $this->postJson('/api/secretary/users', [])->assertForbidden();
        }
    }

    public function test_superadmin_clerk_context_can_create_in_selected_church(): void
    {
        $church = Church::create(['name' => 'Selected']);
        Sanctum::actingAs(User::factory()->create(['role' => 'superadmin', 'church_id' => null]));
        $headers = ['X-Church-Context' => (string) $church->id];
        $this->postJson('/api/secretary/departments', ['name' => 'Music'], $headers)
            ->assertCreated()->assertJsonPath('church_id', $church->id);
        $this->postJson('/api/secretary/users', ['name' => 'Clerk', 'email' => 'clerk@example.test',
            'password' => 'Test-password-123', 'role' => 'secretary'], $headers)
            ->assertCreated()->assertJsonPath('church_id', $church->id);
    }
}
