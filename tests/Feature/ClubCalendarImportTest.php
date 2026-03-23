<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\Objective;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubCalendarImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_maps_missing_club_objectives_during_import(): void
    {
        config()->set('services.integration.token', 'club-token');

        $church = Church::create([
            'name' => 'Iglesia Central',
            'slug' => 'iglesia-central',
        ]);

        $department = Department::create([
            'church_id' => $church->id,
            'name' => 'Pathfinders',
            'color' => '#0055aa',
            'is_club' => true,
        ]);

        User::create([
            'name' => 'Secretary',
            'email' => 'secretary@example.com',
            'password' => 'password',
            'church_id' => $church->id,
            'department_id' => $department->id,
            'role' => User::ROLE_SECRETARY,
        ]);

        $response = $this
            ->withHeader('X-Integration-Token', 'club-token')
            ->postJson('/api/integrations/clubs/calendar', [
                'church_slug' => $church->slug,
                'calendar_year' => 2026,
                'timezone' => 'America/New_York',
                'club_type' => 'pathfinders',
                'plan_name' => 'Plan anual Pathfinders 2026',
                'publish_to_feed' => true,
                'events' => [[
                    'external_id' => 'workplan-event-15',
                    'title' => 'Camporee planning',
                    'description' => 'Planning meeting for regional camporee',
                    'location' => 'Club hall',
                    'start_at' => '2026-04-12T16:00:00',
                    'end_at' => '2026-04-12T18:00:00',
                    'department_id' => $department->id,
                    'objective_id' => 23,
                    'objective_name' => 'Fortalecer liderazgo juvenil',
                    'objective_description' => 'Desarrollar liderazgo y servicio juvenil.',
                    'objective_evaluation_metrics' => 'Participacion y seguimiento trimestral.',
                    'is_special' => true,
                    'club_type' => 'pathfinders',
                    'plan_name' => 'Plan anual Pathfinders 2026',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('skipped', 0);

        $objective = Objective::query()->where('department_id', $department->id)->first();

        $this->assertNotNull($objective);
        $this->assertSame('Fortalecer liderazgo juvenil', $objective->name);
        $this->assertSame('Desarrollar liderazgo y servicio juvenil.', $objective->description);
        $this->assertSame('Participacion y seguimiento trimestral.', $objective->evaluation_metrics);
        $this->assertSame('clubs', $objective->external_source);
        $this->assertSame('23', $objective->external_id);

        $event = Event::query()->where('external_id', 'workplan-event-15')->first();

        $this->assertNotNull($event);
        $this->assertSame($objective->id, $event->objective_id);
        $this->assertSame('Fortalecer liderazgo juvenil', $event->objective_name_snapshot);
    }
}
