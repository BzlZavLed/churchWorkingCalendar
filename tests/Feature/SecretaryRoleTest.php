<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\Objective;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecretaryRoleTest extends TestCase
{
    use RefreshDatabase;

    private function seedChurch(): array
    {
        $church = Church::create(['name' => 'Main Church']);
        $deptA = Department::create(['church_id' => $church->id, 'name' => 'Dept A']);
        $deptB = Department::create(['church_id' => $church->id, 'name' => 'Dept B']);

        $objectiveA = Objective::create([
            'department_id' => $deptA->id,
            'name' => 'Objective A',
            'description' => 'Desc A',
            'evaluation_metrics' => 'Metrics A',
        ]);

        $objectiveB = Objective::create([
            'department_id' => $deptB->id,
            'name' => 'Objective B',
            'description' => 'Desc B',
            'evaluation_metrics' => 'Metrics B',
        ]);

        return [$church, $deptA, $deptB, $objectiveA, $objectiveB];
    }

    private function createEvent(
        Department $department,
        Objective $objective,
        User $creator,
        string $start,
        string $end,
        string $reviewStatus = 'pending'
    ): Event {
        return Event::create([
            'department_id' => $department->id,
            'objective_id' => $objective->id,
            'title' => 'Event ' . $objective->name,
            'start_at' => Carbon::parse($start)->utc(),
            'end_at' => Carbon::parse($end)->utc(),
            'status' => 'locked',
            'review_status' => $reviewStatus,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    public function test_secretary_cannot_create_events(): void
    {
        [$church, $deptA, , $objectiveA] = $this->seedChurch();

        $secretary = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'church_id' => $church->id,
        ]);

        Sanctum::actingAs($secretary);

        $start = Carbon::parse('2026-01-10 09:00:00')->utc();
        $end = Carbon::parse('2026-01-10 10:00:00')->utc();

        $response = $this->postJson('/api/events/hold', [
            'department_id' => $deptA->id,
            'objective_id' => $objectiveA->id,
            'title' => 'Test Event',
            'start_at' => $start->toISOString(),
            'end_at' => $end->toISOString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_secretary_can_view_events_for_all_departments_in_church(): void
    {
        [$church, $deptA, $deptB, $objectiveA, $objectiveB] = $this->seedChurch();

        $creator = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $deptA->id,
        ]);

        $this->createEvent($deptA, $objectiveA, $creator, '2026-01-10 09:00:00', '2026-01-10 10:00:00');
        $this->createEvent($deptB, $objectiveB, $creator, '2026-01-11 09:00:00', '2026-01-11 10:00:00');

        $secretary = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'church_id' => $church->id,
        ]);

        Sanctum::actingAs($secretary);

        $response = $this->getJson('/api/events?start=2026-01-01&end=2026-01-31');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_secretary_can_review_event_with_note(): void
    {
        [$church, $deptA, , $objectiveA] = $this->seedChurch();

        $creator = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $deptA->id,
        ]);

        $event = $this->createEvent($deptA, $objectiveA, $creator, '2026-01-10 09:00:00', '2026-01-10 10:00:00');

        $secretary = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'church_id' => $church->id,
        ]);

        Sanctum::actingAs($secretary);

        $response = $this->postJson("/api/events/{$event->id}/review", [
            'review_status' => 'approved',
            'review_note' => 'Approved by secretary.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'review_status' => 'approved',
            'final_validation' => 'accepted',
        ]);
        $this->assertDatabaseHas('event_histories', [
            'event_id' => $event->id,
            'action' => 'review',
        ]);
    }

    public function test_secretary_can_add_notes_without_status_change(): void
    {
        [$church, $deptA, , $objectiveA] = $this->seedChurch();

        $creator = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $deptA->id,
        ]);

        $event = $this->createEvent($deptA, $objectiveA, $creator, '2026-01-10 09:00:00', '2026-01-10 10:00:00');

        $secretary = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'church_id' => $church->id,
        ]);

        Sanctum::actingAs($secretary);

        $response = $this->postJson("/api/events/{$event->id}/notes", [
            'note' => 'Please update the location.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('event_notes', [
            'event_id' => $event->id,
            'note' => 'Please update the location.',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'review_status' => 'pending',
        ]);
    }

    public function test_secretary_sees_objectives_for_all_departments(): void
    {
        [$church] = $this->seedChurch();

        $secretary = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'church_id' => $church->id,
        ]);

        Sanctum::actingAs($secretary);

        $response = $this->getJson('/api/objectives');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_secretary_can_filter_events_by_department_objective_and_status(): void
    {
        [$church, $deptA, $deptB, $objectiveA, $objectiveB] = $this->seedChurch();

        $creator = User::factory()->create([
            'church_id' => $church->id,
            'department_id' => $deptA->id,
        ]);

        $this->createEvent($deptA, $objectiveA, $creator, '2026-01-10 09:00:00', '2026-01-10 10:00:00', 'pending');
        $this->createEvent($deptB, $objectiveB, $creator, '2026-01-11 09:00:00', '2026-01-11 10:00:00', 'approved');

        $secretary = User::factory()->create([
            'role' => User::ROLE_SECRETARY,
            'church_id' => $church->id,
        ]);

        Sanctum::actingAs($secretary);

        $response = $this->getJson('/api/events?start=2026-01-01&end=2026-01-31'
            . "&department_id={$deptA->id}&objective_id={$objectiveA->id}&review_status=pending");

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('Event Objective A', $response->json()[0]['title']);
    }
}
