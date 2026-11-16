<?php
namespace Tests\Feature;

use App\Models\{Church, Department, Event, Objective, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ObjectiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_writes_are_scoped_and_clerk_is_read_only(): void
    {
        $church = Church::create(['name' => 'Church']);
        $a = Department::create(['church_id' => $church->id, 'name' => 'Music']);
        $b = Department::create(['church_id' => $church->id, 'name' => 'Youth']);
        $payload = ['name' => 'Training', 'description' => 'Train volunteers', 'evaluation_metrics' => 'Four workshops'];
        $other = Objective::create($payload + ['department_id' => $b->id]);
        $admin = User::factory()->create(['role' => 'admin', 'church_id' => $church->id, 'department_id' => $a->id]);
        Sanctum::actingAs($admin);
        $own = $this->postJson('/api/objectives', $payload)->assertCreated()->json('id');
        $this->getJson('/api/objectives')->assertJsonCount(1)->assertJsonPath('0.id', $own);
        $this->postJson('/api/objectives', $payload + ['department_id' => $b->id])->assertUnprocessable();
        $this->putJson("/api/objectives/{$other->id}", $payload)->assertForbidden();
        $this->deleteJson("/api/objectives/{$other->id}")->assertForbidden();
        $this->getJson("/api/objectives/{$other->id}/events")->assertForbidden();
        $this->putJson("/api/objectives/{$own}", array_replace($payload, ['name' => 'Updated']))->assertOk();
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $church->id, 'department_id' => $a->id]));
        $this->getJson('/api/objectives')->assertJsonCount(2);
        $this->postJson('/api/objectives', $payload)->assertForbidden();
        $this->putJson("/api/objectives/{$own}", $payload)->assertForbidden();
        $this->deleteJson("/api/objectives/{$own}")->assertForbidden();
        $this->getJson("/api/objectives/{$other->id}/events")->assertOk();
        Sanctum::actingAs($admin);
        $this->deleteJson("/api/objectives/{$own}")->assertNoContent();
    }

    public function test_linked_events_exclude_holds_and_respect_church_boundaries(): void
    {
        $church = Church::create(['name' => 'Church']);
        $dept = Department::create(['church_id' => $church->id, 'name' => 'Music']);
        $admin = User::factory()->create(['role' => 'admin', 'church_id' => $church->id, 'department_id' => $dept->id]);
        $objective = Objective::create(['department_id' => $dept->id, 'name' => 'Training', 'description' => 'Train', 'evaluation_metrics' => 'Four']);
        foreach (['approved', 'denied', 'pending'] as $review) {
            Event::create(['department_id' => $dept->id, 'objective_id' => $objective->id, 'title' => $review, 'start_at' => '2026-12-01 10:00:00', 'end_at' => '2026-12-01 11:00:00', 'status' => $review === 'pending' ? 'hold' : 'locked', 'review_status' => $review, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        }
        Sanctum::actingAs($admin);
        $this->getJson('/api/objectives')->assertJsonPath('0.events_count', 2)->assertJsonPath('0.approved_events_count', 1);
        $this->getJson("/api/objectives/{$objective->id}/events")->assertJsonCount(2);
        $this->deleteJson("/api/objectives/{$objective->id}")->assertUnprocessable();
        $otherChurch = Church::create(['name' => 'Other']);
        Sanctum::actingAs(User::factory()->create(['role' => 'secretary', 'church_id' => $otherChurch->id]));
        $this->getJson('/api/objectives')->assertJsonCount(0);
        $this->getJson("/api/objectives/{$objective->id}/events")->assertForbidden();
    }
}
