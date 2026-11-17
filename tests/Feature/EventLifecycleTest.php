<?php

namespace Tests\Feature;

use App\Models\{Church, Department, Event, Objective, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $church = Church::create(['name' => 'Church', 'slug' => 'church']);
        $department = Department::create(['name' => 'Music', 'church_id' => $church->id]);
        $member = User::factory()->create(['role' => 'member', 'church_id' => $church->id, 'department_id' => $department->id]);
        $clerk = User::factory()->create(['role' => 'secretary', 'church_id' => $church->id]);
        $objective = Objective::create(['department_id' => $department->id, 'name' => 'Worship', 'description' => 'Music', 'evaluation_metrics' => 'Attendance']);
        $event = Event::create(['department_id' => $department->id, 'objective_id' => $objective->id, 'title' => 'Service', 'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(), 'status' => 'locked', 'review_status' => 'pending', 'created_by' => $member->id, 'updated_by' => $member->id]);
        return [$church, $department, $member, $clerk, $event];
    }

    public function test_conference_priority_import_is_atomic_and_private(): void
    {
        [$church, $department, $member, $clerk, $event] = $this->context();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status'=>'approved'])->assertOk();
        $payload=['title'=>'Conference', 'start_at'=>$event->start_at->toIso8601String(), 'end_at'=>$event->end_at->toIso8601String()];
        $id=$this->postJson('/api/conference-events',$payload)->assertCreated()->assertJsonPath('department_id',null)->json('id');
        $this->assertSame('changes_requested',$event->fresh()->review_status);
        $this->get('/api/public/churches/church/events.ics')->assertDontSee('SUMMARY:Conference',false)->assertDontSee('SUMMARY:Service',false);
        $this->getJson('/api/events?start='.$event->start_at->copy()->subDay()->toDateString().'&end='.$event->end_at->copy()->addDay()->toDateString())->assertJsonCount(2);
        $this->postJson("/api/events/$id/review",['review_status'=>'approved'])->assertForbidden();
        Sanctum::actingAs($member);
        $this->postJson('/api/conference-events',$payload)->assertForbidden();
        $this->patchJson("/api/events/{$event->id}",['title'=>'Still conflicting'])->assertUnprocessable();
        $this->postJson('/api/events/hold',$payload)->assertUnprocessable();
        $this->patchJson("/api/events/{$event->id}",['start_at'=>$event->start_at->copy()->addDays(2)->toIso8601String(),'end_at'=>$event->end_at->copy()->addDays(2)->toIso8601String()])->assertOk()->assertJsonPath('review_status','resubmitted');
        Sanctum::actingAs($clerk);
        $csv="title,start_at,end_at\nAnother,2030-01-01T10:00:00Z,2030-01-01T11:00:00Z\nInvalid,not-a-date,2030-01-01T11:00:00Z";
        $this->post('/api/conference-events/import',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('test.csv',$csv)],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertDatabaseMissing('events',['title'=>'Another']);
        $csv="title,start_at,end_at\nAnother,2030-01-01T10:00:00Z,2030-01-01T11:00:00Z";
        $this->post('/api/conference-events/import',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('test.csv',$csv)],['Accept'=>'application/json'])->assertCreated()->assertJsonPath('created',1);
        $this->post('/api/conference-events/import',['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('test.csv',$csv)],['Accept'=>'application/json'])->assertCreated()->assertJsonPath('skipped',1);
        $this->postJson("/api/conference-events/$id/publish")->assertOk()->assertJsonPath('changed',1);
        $this->get('/api/public/churches/church/events.ics')->assertSee('SUMMARY:Conference',false)->assertDontSee('SUMMARY:Another',false);
        $this->postJson('/api/conference-events/publish-all')->assertOk()->assertJsonPath('changed',1);
        $this->get('/api/public/churches/church/events.ics')->assertSee('SUMMARY:Another',false);
        $this->postJson("/api/conference-events/$id/publish",['published'=>false])->assertOk();
        $this->get('/api/public/churches/church/events.ics')->assertDontSee('SUMMARY:Conference',false);
        $this->deleteJson("/api/conference-events/$id")->assertOk();
        $this->assertFalse(Event::hasConflict(\Carbon\Carbon::parse($payload['start_at']),\Carbon\Carbon::parse($payload['end_at']),null,$church->id));
    }

    public function test_acceptance_publishes_and_clerk_reopening_removes_event_until_reaccepted(): void
    {
        [, , $member, $clerk, $event] = $this->context();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'approved', 'publish_to_feed' => false])->assertOk()->assertJsonPath('publish_to_feed', true);
        $this->get('/api/public/churches/church/events.ics')->assertSee('SUMMARY:Service', false);
        Sanctum::actingAs($member);
        $this->patchJson("/api/events/{$event->id}", ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/events/{$event->id}")->assertForbidden();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'changes_requested', 'review_note' => 'Please update'])->assertOk()->assertJsonPath('publish_to_feed', false);
        $this->get('/api/public/churches/church/events.ics')->assertDontSee('SUMMARY:Service', false);
        Sanctum::actingAs($member);
        $this->patchJson("/api/events/{$event->id}", ['title' => 'Changed'])->assertOk()->assertJsonPath('review_status', 'resubmitted')->assertJsonPath('final_validation', null)->assertJsonPath('accepted_at', null);
    }

    public function test_rejection_releases_slot_and_other_church_does_not_block(): void
    {
        [$church, , $member, $clerk, $event] = $this->context();
        $this->assertTrue(Event::hasConflict($event->start_at, $event->end_at, null, $church->id));
        $other = Church::create(['name' => 'Other']);
        $this->assertFalse(Event::hasConflict($event->start_at, $event->end_at, null, $other->id));
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'denied', 'review_note' => 'Rejected'])->assertOk();
        $this->assertFalse(Event::hasConflict($event->start_at, $event->end_at, null, $church->id));
        Sanctum::actingAs($member);
        $this->patchJson("/api/events/{$event->id}", ['title' => 'Retry'])->assertForbidden();
    }

    public function test_holds_can_be_extended_only_while_active(): void
    {
        [, , $member, $clerk, $event] = $this->context();
        $event->update(['status' => 'hold', 'expires_at' => now()->addSeconds(30)]);
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/extend")->assertForbidden();
        Sanctum::actingAs($member);
        $this->postJson("/api/events/{$event->id}/extend")->assertOk();
        $this->assertTrue($event->fresh()->expires_at->equalTo(now()->addMinutes(5)->startOfSecond()));
        $this->travel(6)->minutes();
        $this->postJson("/api/events/{$event->id}/extend")->assertForbidden();
    }

    public function test_confirmed_event_objective_cannot_be_deleted(): void
    {
        [, , $member, , $event] = $this->context();
        Sanctum::actingAs($member);
        $this->deleteJson("/api/objectives/{$event->objective_id}")->assertUnprocessable();
    }
    public function test_open_request_is_idempotent_and_only_clerk_can_reopen(): void
    {
        [, $department, $member, $clerk, $event] = $this->context();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'approved'])->assertOk();
        Sanctum::actingAs($member);
        $this->postJson("/api/events/{$event->id}/request-open")->assertOk()->assertJsonPath('review_status', 'approved')->assertJsonPath('publish_to_feed', true);
        $this->postJson("/api/events/{$event->id}/request-open")->assertOk();
        $this->assertSame(1, $event->histories()->where('action', 'opening_requested')->count());
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'changes_requested', 'review_note' => 'Open'])->assertForbidden();
        $outsider = User::factory()->create(['role' => 'member', 'department_id' => null]);
        Sanctum::actingAs($outsider);
        $this->postJson("/api/events/{$event->id}/request-open")->assertForbidden();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'changes_requested', 'review_note' => 'Open'])->assertOk()->assertJsonPath('open_requested_at', null);
        Sanctum::actingAs($member);
        $this->patchJson("/api/events/{$event->id}", ['title' => 'Revision'])->assertOk()->assertJsonPath('id', $event->id)->assertJsonPath('review_status', 'resubmitted');
        $this->assertSame(1, Event::count());
        $this->patchJson("/api/events/{$event->id}", ['title' => 'Unrequested edit'])->assertForbidden();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'approved'])->assertOk();
        $this->assertSame(['accepted', 'opening_requested', 'reopened', 'resubmitted', 'accepted'], $event->histories()->orderBy('id')->pluck('action')->all());
    }

    public function test_initial_submission_is_recorded_even_without_a_note(): void
    {
        [, , $member, , $event] = $this->context();
        $event->update(['status' => 'hold', 'expires_at' => now()->addMinutes(5)]);
        Sanctum::actingAs($member);
        $this->postJson("/api/events/{$event->id}/lock")->assertOk()->assertJsonPath('histories.0.action', 'submitted');
        $this->postJson("/api/events/{$event->id}/request-open")->assertUnprocessable();
    }

    public function test_notes_are_a_conversation_and_never_change_accepted_status(): void
    {
        [, , $member, $clerk, $event] = $this->context();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'approved', 'review_note' => 'Approved with thanks'])->assertOk();
        $this->assertDatabaseHas('event_notes', ['event_id' => $event->id, 'kind' => 'accepted', 'note' => 'Approved with thanks']);
        $this->assertDatabaseHas('event_histories', ['event_id' => $event->id, 'action' => 'accepted', 'note' => null]);
        $this->postJson("/api/events/{$event->id}/notes", ['note' => 'Please consider another location'])->assertCreated();
        Sanctum::actingAs($member);
        $this->postJson("/api/events/{$event->id}/notes", ['note' => 'First response'])->assertCreated();
        $this->postJson("/api/events/{$event->id}/notes", ['note' => 'Another response'])->assertCreated();
        $this->assertSame('approved', $event->fresh()->review_status);
        $this->assertTrue($event->fresh()->publish_to_feed);
        $this->assertSame(1, $event->histories()->count());
        $this->assertSame(4, $event->notes()->count());
    }

    public function test_change_request_note_is_in_conversation_and_unseen_list(): void
    {
        [, , $member, $clerk, $event] = $this->context();
        Sanctum::actingAs($clerk);
        $this->postJson("/api/events/{$event->id}/review", ['review_status' => 'changes_requested', 'review_note' => 'Update location'])
            ->assertOk()->assertJsonPath('notes.0.kind', 'changes_requested');
        Sanctum::actingAs($member);
        $this->getJson('/api/notes/unseen')->assertOk()->assertJsonPath('count', 1);
        $this->postJson("/api/events/{$event->id}/notes", ['note' => 'Acknowledged'])->assertCreated();
        $this->assertSame('changes_requested', $event->fresh()->review_status);
        $this->patchJson("/api/events/{$event->id}", ['location' => 'New location'])->assertOk();
        $this->assertSame(2, $event->notes()->count());
    }

    public function test_notes_reject_cross_church_and_cross_department_writes(): void
    {
        [, , , , $event] = $this->context();
        $other = Church::create(['name' => 'Other']);
        $outsider = User::factory()->create(['role' => 'secretary', 'church_id' => $other->id]);
        Sanctum::actingAs($outsider);
        $this->postJson("/api/events/{$event->id}/notes", ['note' => 'Forbidden'])->assertForbidden();
        $outsider->update(['role' => 'member']);
        $this->postJson("/api/events/{$event->id}/notes", ['note' => 'Forbidden'])->assertForbidden();
        $this->assertSame(0, $event->notes()->count());
    }

    public function test_legacy_review_comments_and_replies_are_preserved_by_migration(): void
    {
        [, , $member, $clerk, $event] = $this->context();
        $migration = require database_path('migrations/2026_11_16_000002_unify_event_notes.php');
        $migration->down();
        $now = now();
        \Illuminate\Support\Facades\DB::table('event_histories')->insert([
            'event_id' => $event->id, 'user_id' => $clerk->id, 'action' => 'changes_requested',
            'note' => 'Legacy change request', 'created_at' => $now, 'updated_at' => $now,
        ]);
        \Illuminate\Support\Facades\DB::table('event_notes')->insert([
            'event_id' => $event->id, 'user_id' => $clerk->id, 'note' => 'Legacy general note',
            'reply' => 'Legacy reply', 'reply_user_id' => $member->id, 'replied_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $event->update(['review_note' => 'Legacy review without history', 'reviewed_by' => $clerk->id]);
        $migration->up();
        $this->assertEqualsCanonicalizing(
            ['Legacy change request', 'Legacy general note', 'Legacy reply', 'Legacy review without history'],
            $event->notes()->pluck('note')->all()
        );
        $this->assertSame('Legacy change request', $event->histories()->first()->note);
        $this->assertDatabaseHas('event_notes', ['note' => 'Legacy reply', 'author_role' => 'member']);
    }

}
