<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Church, Department, Event, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_drilldown_scopes_data_and_applies_clerk_and_department_permissions(): void
    {
        $church = Church::create(['name' => 'Selected']);
        $other = Church::create(['name' => 'Other']);
        $department = Department::create(['name' => 'Music', 'church_id' => $church->id]);
        $otherDepartment = Department::create(['name' => 'Youth', 'church_id' => $other->id]);
        $actor = User::factory()->create(['role' => 'superadmin', 'church_id' => null, 'department_id' => null]);
        Sanctum::actingAs($actor);
        $payload = ['start_at' => now()->addDay()->toIso8601String(), 'end_at' => now()->addDay()->addHour()->toIso8601String()];
        $clerkHeaders = ['X-Church-Context' => (string) $church->id];
        $departmentHeaders = $clerkHeaders + ['X-Department-Context' => (string) $department->id];

        $this->postJson('/api/events/hold', $payload + ['department_id' => $department->id], $clerkHeaders)->assertForbidden();
        $this->getJson('/api/secretary/departments', $clerkHeaders)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $department->id);
        $this->postJson('/api/events/hold', $payload, $departmentHeaders)->assertCreated()->assertJsonPath('department_id', $department->id)->assertJsonPath('created_by', $actor->id);
        $event = Event::firstOrFail();
        $this->getJson('/api/secretary/departments', $departmentHeaders)->assertForbidden();
        $this->postJson('/api/events/hold', $payload + ['department_id' => $otherDepartment->id], $departmentHeaders)->assertUnprocessable();
        $this->getJson('/api/events?start='.urlencode(now()->toIso8601String()).'&end='.urlencode(now()->addDays(3)->toIso8601String()), ['X-Church-Context' => (string) $other->id])->assertOk()->assertJsonCount(0);
        $this->getJson('/api/events?start='.urlencode(now()->toIso8601String()).'&end='.urlencode(now()->addDays(3)->toIso8601String()), $clerkHeaders)->assertOk()->assertJsonCount(1);
        $this->assertSame('superadmin', $actor->fresh()->role);
        $this->assertNull($actor->fresh()->department_id);
        $audit = AuditLog::where('path', '/api/events/hold')->firstOrFail();
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertSame($department->id, $audit->metadata['viewing_context']['department_id']);
        $this->getJson('/api/superadmin/churches')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_pastor_context_can_configure_selected_church_form_only(): void
    {
        $church = Church::create(['name' => 'Selected']);
        $other = Church::create(['name' => 'Other']);
        $actor = User::factory()->create(['role' => 'superadmin', 'church_id' => null]);
        Sanctum::actingAs($actor);
        $headers = ['X-Church-Context' => (string) $church->id, 'X-Role-Context' => 'pastor'];
        $this->getJson('/api/visitor-form', $headers)->assertOk()->assertJsonPath('version', 0);
        $this->postJson('/api/visitor-form', ['base_version' => 0, 'fields' => []], $headers)->assertCreated()->assertJsonPath('version', 1);
        $this->assertDatabaseHas('visitor_forms', ['church_id' => $church->id, 'published_by' => $actor->id]);
        $this->getJson('/api/visitor-form', array_merge($headers, ['X-Church-Context' => (string) $other->id]))->assertOk()->assertJsonPath('version', 0);
        $this->getJson('/api/secretary/departments', $headers)->assertForbidden();
        $this->postJson('/api/visitor-form', ['base_version' => 1, 'fields' => []], ['X-Church-Context' => (string) $church->id])->assertForbidden();
        $this->getJson('/api/visitor-form', $headers + ['X-Department-Context' => '1'])->assertUnprocessable();
        $this->getJson('/api/visitor-form', array_merge($headers, ['X-Role-Context' => 'superadmin']))->assertUnprocessable();
        $this->assertSame('superadmin', $actor->fresh()->role);
        $this->assertNull($actor->fresh()->church_id);
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $church->id]));
        $this->getJson('/api/visitor-form', $headers)->assertForbidden();
    }

    public function test_context_rejects_regular_users_and_departments_from_another_church(): void
    {
        $church = Church::create(['name' => 'Selected']);
        $other = Church::create(['name' => 'Other']);
        $department = Department::create(['name' => 'Youth', 'church_id' => $other->id]);
        $actor = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($actor);
        $this->getJson('/api/objectives', ['X-Church-Context' => (string) $church->id, 'X-Department-Context' => (string) $department->id])->assertNotFound();
        $this->getJson('/api/objectives', ['X-Department-Context' => (string) $department->id])->assertUnprocessable();
        $this->getJson('/api/objectives', ['X-Church-Context' => 'invalid'])->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create(['role' => 'member']));
        $this->getJson('/api/objectives', ['X-Church-Context' => (string) $church->id])->assertForbidden();
    }
}
