<?php
namespace Tests\Feature;

use App\Models\{Church, Department, Meeting, MeetingPoint, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private function setupMeeting(): array
    {
        $this->travelTo(Carbon::parse('2026-12-01 12:00:00 UTC'));
        $church = Church::create(['name' => 'Main']);
        $a = Department::create(['church_id' => $church->id, 'name' => 'Music']);
        $b = Department::create(['church_id' => $church->id, 'name' => 'Youth']);
        $clerk = User::factory()->create(['role' => 'secretary', 'church_id' => $church->id, 'department_id' => null]);
        $admin = User::factory()->create(['role' => 'admin', 'church_id' => $church->id, 'department_id' => $a->id]);
        $other = User::factory()->create(['role' => 'admin', 'church_id' => $church->id, 'department_id' => $b->id]);
        Sanctum::actingAs($clerk);
        $meeting = $this->postJson('/api/meetings', ['meeting_date' => '2026-12-05', 'planned_start_at' => '2026-12-05T12:00:00Z', 'location' => 'Church'])->assertCreated()->json('id');
        return [$church, $clerk, $admin, $other, Meeting::findOrFail($meeting)];
    }
    private function point(Meeting $meeting, User $user, string $title = 'Proposal'): int
    {
        Sanctum::actingAs($user);
        return $this->postJson("/api/meetings/{$meeting->id}/points", ['title' => $title, 'description' => 'Original proposal'])->assertCreated()->json('id');
    }
    private function accept(int $id, User $clerk): void
    {
        Sanctum::actingAs($clerk);
        $this->postJson("/api/meeting-points/$id/review", ['status' => 'accepted'])->assertOk();
    }
    public function test_exact_deadline_freezes_submissions_reviews_and_order_and_shares_only_accepted_agenda(): void
    {
        [, $clerk, $admin, $other, $meeting] = $this->setupMeeting();
        $id = $this->point($meeting, $admin);
        $rejected = $this->point($meeting, $other, 'Rejected request');
        $this->accept($id, $clerk);
        $this->postJson("/api/meeting-points/$rejected/review", ['status'=>'rejected'])->assertOk();
        $this->postJson("/api/meetings/{$meeting->id}/close-agenda")->assertUnprocessable();
        Sanctum::actingAs($other);
        $this->getJson("/api/meetings/{$meeting->id}/points?scope=agenda")->assertJsonCount(0);
        $this->travelTo($meeting->planned_start_at->copy()->subDay());
        $this->postJson("/api/meetings/{$meeting->id}/points", ['title'=>'Too late','description'=>'No'])->assertUnprocessable();
        $this->getJson("/api/meetings/{$meeting->id}/points?scope=agenda")->assertJsonCount(1)->assertJsonPath('0.id',$id)->assertJsonMissingPath('0.history')->assertJsonMissingPath('0.review_note')->assertJsonMissingPath('0.final_status');
        $this->assertNotNull($meeting->fresh()->agenda_closed_at);
        Sanctum::actingAs($clerk);
        $this->postJson("/api/meeting-points/$rejected/review", ['status'=>'accepted'])->assertUnprocessable();
        $this->postJson("/api/meetings/{$meeting->id}/points/reorder", ['point_ids'=>[$rejected,$id]])->assertUnprocessable();
    }
    public function test_review_resubmits_same_point_and_clerk_can_submit_without_department(): void
    {
        [, $clerk, $admin, $other, $meeting] = $this->setupMeeting();
        $id = $this->point($meeting, $admin);
        Sanctum::actingAs($clerk);
        $this->postJson("/api/meeting-points/$id/review", ['status'=>'needs_update'])->assertUnprocessable();
        $this->postJson("/api/meeting-points/$id/review", ['status'=>'needs_update','review_note'=>'Add details'])->assertOk();
        Sanctum::actingAs($other);
        $this->patchJson("/api/meeting-points/$id", ['title'=>'Changed','description'=>'Details'])->assertForbidden();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/meeting-points/$id", ['title'=>'Changed','description'=>'Details'])->assertOk()->assertJsonPath('id',$id)->assertJsonPath('status','submitted');
        $this->assertDatabaseCount('meeting_points',1);
        $clerkPoint=$this->point($meeting,$clerk,'Clerk proposal');
        $this->assertNull(MeetingPoint::find($clerkPoint)->department_id);
        $this->assertSame('accepted',MeetingPoint::find($clerkPoint)->status);
        $this->assertCount(3,MeetingPoint::find($id)->history);
    }
    public function test_live_decisions_closure_privacy_attendance_and_public_pdf(): void
    {
        [, $clerk, $admin, $other, $meeting] = $this->setupMeeting();
        $id=$this->point($meeting,$admin);$rejected=$this->point($meeting,$other,'Not on agenda');
        $this->accept($id,$clerk);
        $this->postJson("/api/meeting-points/$rejected/review",['status'=>'rejected'])->assertOk();
        $this->postJson("/api/meetings/{$meeting->id}/start")->assertUnprocessable();
        $this->postJson("/api/meetings/{$meeting->id}/adjourn")->assertUnprocessable();
        $this->patchJson("/api/meetings/{$meeting->id}",['summary_public'=>true])->assertUnprocessable();
        $this->travelTo($meeting->planned_start_at);
        $this->postJson("/api/meetings/{$meeting->id}/start")->assertOk();
        $this->patchJson("/api/meetings/{$meeting->id}",['attendance'=>[['name'=>'Alice','status'=>'present'],['name'=>'Bob','status'=>'absent'],['name'=>'Carol','status'=>'online']], 'opening_prayer'=>'Alice','opening_remarks'=>'Welcome'])->assertOk();
        $this->postJson("/api/meetings/{$meeting->id}/adjourn")->assertUnprocessable();
        $this->postJson("/api/meeting-points/$rejected/activate")->assertUnprocessable();
        $this->postJson("/api/meeting-points/$id/finalize",['final_status'=>'approved'])->assertUnprocessable();
        $this->postJson("/api/meeting-points/$id/activate")->assertOk();
        $this->postJson("/api/meeting-points/$id/finalize",['final_status'=>'approved','final_note'=>'Private decision'])->assertOk();
        Sanctum::actingAs($other);
        $this->getJson("/api/meetings/{$meeting->id}")->assertJsonMissingPath('attendance')->assertJsonMissingPath('points')->assertJsonMissingPath('summary_generated');
        $this->getJson("/api/meetings/{$meeting->id}/points?scope=agenda")->assertJsonMissingPath('0.final_status')->assertJsonMissingPath('0.final_note');
        Sanctum::actingAs($admin);
        $this->getJson("/api/meetings/{$meeting->id}/points")->assertJsonPath('0.final_status','approved')->assertJsonMissingPath('0.meeting');
        Sanctum::actingAs($clerk);
        $this->postJson("/api/meetings/{$meeting->id}/adjourn")->assertOk()->assertJsonPath('summary_public',false);
        $this->postJson("/api/meeting-points/$id/finalize",['final_status'=>'denied'])->assertUnprocessable();
        $this->postJson("/api/meetings/{$meeting->id}/notes",['note'=>'Too late'])->assertUnprocessable();
        Sanctum::actingAs($admin);
        $this->getJson('/api/meetings')->assertJsonMissingPath('0.summary_generated')->assertJsonMissingPath('0.attendance');
        $this->getJson('/api/meeting-points/report')->assertJsonCount(1)->assertJsonPath('0.final_status','approved')->assertJsonMissingPath('0.meeting.summary_generated');
        $this->getJson("/api/meetings/{$meeting->id}/summary.pdf")->assertForbidden();
        Sanctum::actingAs($clerk);
        $this->patchJson("/api/meetings/{$meeting->id}",['summary_public'=>true])->assertOk();
        Sanctum::actingAs($other);
        $this->getJson('/api/meetings')->assertJsonPath('0.attendance.2.status','online')->assertJsonPath('0.summary_public',true);
        $this->get("/api/meetings/{$meeting->id}/summary.pdf")->assertOk()->assertHeader('content-type','application/pdf');
        // Even public minutes do not expose other departments' individual decision API records.
        $this->getJson("/api/meetings/{$meeting->id}/points?scope=agenda")->assertJsonMissingPath('0.final_status');
    }
    public function test_deferred_points_and_revisions_carry_once_without_rewriting_original_vote(): void
    {
        [, $clerk, $admin, , $meeting] = $this->setupMeeting();
        $deferred=$this->point($meeting,$admin,'Deferred');$updates=$this->point($meeting,$admin,'Needs revision');
        $this->accept($deferred,$clerk);$this->accept($updates,$clerk);
        $this->travelTo($meeting->planned_start_at);
        $this->postJson("/api/meetings/{$meeting->id}/start")->assertOk();
        foreach ([$deferred=>'postponed',$updates=>'needs_update'] as $id=>$outcome) {
            $this->postJson("/api/meeting-points/$id/activate")->assertOk();
            $this->postJson("/api/meeting-points/$id/finalize",['final_status'=>$outcome,'final_note'=>'Bring more details'])->assertOk();
        }
        $this->postJson("/api/meetings/{$meeting->id}/adjourn")->assertOk();
        Sanctum::actingAs($admin);
        $this->getJson('/api/meeting-points/report')->assertJsonPath('0.continuations',[]);
        $this->patchJson("/api/meeting-points/$updates",['title'=>'Revised','description'=>'New details'])->assertOk()->assertJsonPath('title','Needs revision')->assertJsonPath('revision.title','Revised')->assertJsonPath('final_status','needs_update');
        Sanctum::actingAs($clerk);
        $next=$this->postJson('/api/meetings',['meeting_date'=>'2026-12-12','planned_start_at'=>'2026-12-12T12:00:00Z'])->assertCreated()->json('id');
        $this->getJson("/api/meetings/$next/points")->assertJsonCount(2)->assertJsonPath('0.related_meeting_point_id',$deferred)->assertJsonPath('0.status','submitted')->assertJsonPath('1.title','Revised')->assertJsonPath('1.status','submitted');
        $later=$this->postJson('/api/meetings',['meeting_date'=>'2026-12-19','planned_start_at'=>'2026-12-19T12:00:00Z'])->assertCreated()->json('id');
        $this->getJson("/api/meetings/$later/points")->assertJsonCount(0);
        Sanctum::actingAs($admin);
        $this->patchJson("/api/meeting-points/$updates",['title'=>'Wrong record','description'=>'No'])->assertUnprocessable();
        $this->assertDatabaseCount('meeting_points',4);
    }
    public function test_other_church_and_department_cannot_manage_meeting_or_read_reports(): void
    {
        [, $clerk, $admin, , $meeting] = $this->setupMeeting();
        $id=$this->point($meeting,$admin);
        $this->postJson("/api/meeting-points/$id/review",['status'=>'accepted'])->assertForbidden();
        $this->postJson("/api/meetings/{$meeting->id}/start")->assertForbidden();
        $outside=Church::create(['name'=>'Outside']);
        Sanctum::actingAs(User::factory()->create(['role'=>'secretary','church_id'=>$outside->id]));
        $this->getJson('/api/meetings')->assertJsonCount(0);
        $this->getJson('/api/meeting-points/report')->assertJsonCount(0);
        $this->getJson("/api/meetings/{$meeting->id}")->assertForbidden();
        $this->postJson("/api/meetings/{$meeting->id}/points",['title'=>'No','description'=>'No'])->assertForbidden();
    }
    public function test_existing_next_meeting_gets_followup_and_unrevised_request_requires_resubmission(): void
    {
        [, $clerk, $admin, , $meeting] = $this->setupMeeting();
        $id=$this->point($meeting,$admin);
        $this->accept($id,$clerk);
        $next=$this->postJson('/api/meetings',['meeting_date'=>'2026-12-12','planned_start_at'=>'2026-12-12T12:00:00Z'])->assertCreated()->json('id');
        $this->travelTo($meeting->planned_start_at);
        $this->artisan('meetings:close-agendas')->assertSuccessful();
        $this->assertNotNull($meeting->fresh()->agenda_closed_at);
        $this->postJson("/api/meetings/{$meeting->id}/start")->assertOk();
        $this->postJson("/api/meeting-points/$id/activate")->assertOk();
        $this->postJson("/api/meeting-points/$id/finalize",['final_status'=>'needs_update','final_note'=>'Revise costs'])->assertOk();
        $this->postJson("/api/meetings/{$meeting->id}/adjourn")->assertOk();
        $this->postJson("/api/meetings/{$meeting->id}/adjourn")->assertOk();
        $copy=MeetingPoint::where('related_meeting_point_id',$id)->sole();
        $this->assertSame($next,$copy->meeting_id);
        $this->assertSame('needs_update',$copy->status);
        Sanctum::actingAs($admin);
        $this->patchJson("/api/meeting-points/{$copy->id}",['title'=>'Updated costs','description'=>'New estimate'])->assertOk()->assertJsonPath('status','submitted');
        $this->assertSame('Proposal',MeetingPoint::find($id)->title);
    }

    public function test_remaining_vote_outcomes_and_superadmin_drilldown_permissions(): void
    {
        [$church, $clerk, $admin, , $meeting] = $this->setupMeeting();
        $denied=$this->point($meeting,$admin,'Deny');$info=$this->point($meeting,$admin,'Information');
        $this->accept($denied,$clerk);$this->accept($info,$clerk);
        $this->travelTo($meeting->planned_start_at);
        $root=User::factory()->create(['role'=>'superadmin','church_id'=>null,'department_id'=>null]);
        Sanctum::actingAs($root);
        $this->withHeaders(['X-Church-Context'=>(string)$church->id,'X-Department-Context'=>(string)$admin->department_id]);
        $this->postJson("/api/meetings/{$meeting->id}/start")->assertForbidden();
        $this->withHeaders(['X-Department-Context'=>'']);
        $this->postJson("/api/meetings/{$meeting->id}/start")->assertOk();
        foreach ([$denied=>'denied',$info=>'informative'] as $id=>$status) {
            $this->postJson("/api/meeting-points/$id/activate")->assertOk();
            $this->postJson("/api/meeting-points/$id/finalize",['final_status'=>$status])->assertOk()->assertJsonPath('final_status',$status);
        }
        $this->postJson("/api/meetings/{$meeting->id}/adjourn")->assertOk();
        $this->assertSame($root->id,MeetingPoint::find($denied)->finalized_by);
    }

    public function test_scheduled_local_date_survives_utc_day_boundary(): void
    {
        [, $clerk] = $this->setupMeeting();
        Sanctum::actingAs($clerk);
        $response=$this->postJson('/api/meetings', ['meeting_date'=>'2026-12-08','planned_start_at'=>'2026-12-08T22:30-05:00'])->assertCreated();
        $meeting=Meeting::findOrFail($response->json('id'));
        $this->assertSame('2026-12-08',$meeting->meeting_date->toDateString());
        $this->assertSame('2026-12-09 03:30',$meeting->planned_start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-12-08 03:30',$meeting->deadlineAt()->format('Y-m-d H:i'));
    }

}
