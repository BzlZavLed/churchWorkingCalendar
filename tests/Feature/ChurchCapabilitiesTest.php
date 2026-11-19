<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchRole,Department,Event,Objective,User};
use App\Services\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ChurchCapabilitiesTest extends TestCase {
 use RefreshDatabase;
 private function role(Church $church,string $base,array $caps): ChurchRole {
  return ChurchRole::create(['church_id'=>$church->id,'key'=>$base,'name'=>$base,'base_role'=>$base,'is_builtin'=>true,'permissions'=>['calendar'=>'edit','meetings'=>'edit'],'capabilities'=>$caps]);
 }
 public function test_clerk_can_create_department_events_only_when_enabled_and_lifecycle_stays_enforced(): void {
  $church=Church::create(['name'=>'One']);$other=Church::create(['name'=>'Other']);
  $dept=Department::create(['church_id'=>$church->id,'name'=>'Music']);$foreign=Department::create(['church_id'=>$other->id,'name'=>'Other']);
  $clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);
  $otherClerk=User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']);
  $objective=Objective::create(['department_id'=>$dept->id,'name'=>'Worship','description'=>'Music','evaluation_metrics'=>'Attendance']);
  $data=['department_id'=>$dept->id,'objective_id'=>$objective->id,'title'=>'Concert','start_at'=>now()->addDays(3)->toIso8601String(),'end_at'=>now()->addDays(3)->addHour()->toIso8601String()];
  Sanctum::actingAs($clerk);$this->postJson('/api/events/hold',$data)->assertForbidden();
  $role=$this->role($church,'secretary',['calendar.create'=>true,'calendar.edit'=>true,'calendar.all_departments'=>true]);
  $this->postJson('/api/events/hold',array_replace($data,['department_id'=>$foreign->id]))->assertUnprocessable();
  $id=$this->postJson('/api/events/hold',$data)->assertCreated()->json('id');
  $this->postJson('/api/events/hold',$data)->assertUnprocessable();
  $this->postJson("/api/events/$id/lock",['title'=>'Concert','objective_id'=>$objective->id])->assertOk();
  $this->postJson("/api/events/$id/review",['review_status'=>'approved'])->assertOk();
  $this->patchJson("/api/events/$id",['title'=>'Cannot edit accepted'])->assertForbidden();
  $this->postJson("/api/events/$id/review",['review_status'=>'changes_requested','review_note'=>'Change title'])->assertOk();
  $this->patchJson("/api/events/$id",['title'=>'Updated concert'])->assertOk()->assertJsonPath('review_status','resubmitted');
  Sanctum::actingAs($otherClerk);$this->postJson('/api/events/hold',array_replace($data,['department_id'=>$foreign->id]))->assertForbidden();
  $this->postJson("/api/events/$id/review",['review_status'=>'approved'])->assertForbidden();
 }
 public function test_pastor_can_receive_clerk_calendar_and_meeting_workflows_without_changing_identity(): void {
  $church=Church::create(['name'=>'One']);$dept=Department::create(['church_id'=>$church->id,'name'=>'Music']);
  $pastor=User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']);
  $role=$this->role($church,'pastor',['calendar.review'=>true,'calendar.conference'=>true,'meetings.manage'=>true]);
  $event=Event::create(['department_id'=>$dept->id,'title'=>'Concert','start_at'=>now()->addDays(10),'end_at'=>now()->addDays(10)->addHour(),'status'=>'locked','review_status'=>'pending','created_by'=>$pastor->id,'updated_by'=>$pastor->id]);
  Sanctum::actingAs($pastor);
  $this->postJson("/api/events/{$event->id}/review",['review_status'=>'approved'])->assertOk();
  $this->postJson("/api/events/{$event->id}/notes",['note'=>'Please read'])->assertCreated()->assertJsonPath('author_role','pastor')->assertJsonPath('author_is_reviewer',true)->assertJsonPath('seen_note',false);
  $this->postJson('/api/conference-events',['title'=>'Conference','start_at'=>now()->addDays(20)->toIso8601String(),'end_at'=>now()->addDays(20)->addHour()->toIso8601String()])->assertCreated()->assertJsonPath('publish_to_feed',false);
  $meeting=$this->postJson('/api/meetings',['meeting_date'=>now()->addDays(5)->toDateString(),'planned_start_at'=>now()->addDays(5)->toIso8601String(),'location'=>'Church'])->assertCreated()->json('id');
  $this->postJson("/api/meetings/$meeting/points",['title'=>'Agenda','description'=>'Review calendar'])->assertCreated();
  $this->postJson("/api/meetings/$meeting/start")->assertOk();
  $this->assertSame('pastor',$pastor->fresh()->role);
  $role->update(['permissions'=>['calendar'=>'read','meetings'=>'read']]);
  $this->postJson("/api/events/{$event->id}/review",['review_status'=>'changes_requested','review_note'=>'No'])->assertForbidden();
  $this->postJson("/api/meetings/$meeting/start")->assertForbidden();
 }
 public function test_reset_is_revision_guarded_church_scoped_and_restores_original_defaults(): void {
  $church=Church::create(['name'=>'One']);$other=Church::create(['name'=>'Other']);
  $role=$this->role($church,'secretary',['calendar.create'=>true]);$otherRole=$this->role($other,'secretary',['calendar.create'=>true]);
  Sanctum::actingAs(User::factory()->create(['role'=>'superadmin']));
  $data=['name'=>$role->name,'revision'=>1,'permissions'=>app(ModuleAccess::class)->defaults('secretary'),'capabilities'=>['calendar.create'=>true],'reset_defaults'=>true];
  $url="/api/superadmin/churches/{$church->id}/roles/{$role->id}";
  $this->putJson($url,$data)->assertOk()->assertJsonPath('capabilities',[])->assertJsonPath('permissions',[]);
  $this->putJson($url,$data)->assertConflict();
  $this->assertTrue($otherRole->fresh()->capabilities['calendar.create']);
  $user=User::factory()->create(['role'=>'secretary','church_id'=>$church->id]);
  $this->assertFalse($user->hasCapability('calendar.create'));$this->assertTrue($user->hasCapability('calendar.conference'));
  $data['revision']=2;$data['reset_defaults']=false;$data['capabilities']=['calendar.create'=>'invalid'];
  $this->putJson($url,$data)->assertUnprocessable();
  $data['capabilities']=['calendar.create'=>true];$this->putJson($url,$data)->assertOk();
  $this->assertTrue($user->hasCapability('calendar.create'));
 }
 public function test_action_overrides_cover_management_modules_and_can_be_revoked(): void {
  $church=Church::create(['name'=>'One']);$pastor=User::factory()->create(['role'=>'pastor','church_id'=>$church->id]);
  $role=$this->role($church,'pastor',['departments.manage'=>true,'users.manage'=>true,'visitors.configure'=>false,'members.manage'=>false]);
  $role->update(['permissions'=>['departments'=>'edit','users'=>'edit']]);Sanctum::actingAs($pastor);
  $this->postJson('/api/secretary/departments',['name'=>'New department'])->assertCreated();
  $this->postJson('/api/secretary/users',[])->assertUnprocessable();
  $this->postJson('/api/visitor-form',['base_version'=>0,'fields'=>[]])->assertForbidden();
  $this->postJson('/api/members/template',['base_version'=>0,'fields'=>[]])->assertForbidden();
  $role->update(['capabilities'=>['departments.manage'=>false,'users.manage'=>false]]);
  $this->postJson('/api/secretary/departments',['name'=>'Not allowed'])->assertForbidden();
  $this->postJson('/api/secretary/users',[])->assertForbidden();
 }

}
