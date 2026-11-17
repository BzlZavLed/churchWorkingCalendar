<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchRole,Department,Event,Objective,User};
use App\Services\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ChurchRoleAccessTest extends TestCase {
 use RefreshDatabase;
 private function permissions(string $level='none'): array {return array_fill_keys(array_keys(ModuleAccess::MODULES),$level);}
 private function role(Church $church,string $base,array $permissions): ChurchRole {
  return ChurchRole::create(['church_id'=>$church->id,'key'=>$base,'name'=>$base,'base_role'=>$base,'permissions'=>$permissions,'is_builtin'=>true]);
 }
 public function test_superadmin_can_manage_church_roles_and_assign_custom_workflows(): void {
  $church=Church::create(['name'=>'One']);$other=Church::create(['name'=>'Two']);$dept=Department::create(['church_id'=>$church->id,'name'=>'Music']);
  $actor=User::factory()->create(['role'=>'superadmin']);$user=User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']);
  Sanctum::actingAs($actor);
  $this->getJson('/api/superadmin/churches/'.$church->id.'/roles')->assertOk()->assertJsonCount(4,'roles');
  $roleId=$this->postJson('/api/superadmin/churches/'.$church->id.'/roles',['name'=>'Pastoral calendar reader','base_role'=>'pastor'])->assertCreated()->json('id');
  $permissions=$this->permissions();$permissions['calendar']='read';
  $this->putJson("/api/superadmin/churches/{$church->id}/roles/$roleId",['name'=>'Pastoral calendar reader','revision'=>1,'permissions'=>$permissions])->assertOk();
  $this->putJson("/api/superadmin/churches/{$church->id}/roles/$roleId",['name'=>'Stale','revision'=>1,'permissions'=>$permissions])->assertConflict();
  $this->putJson("/api/superadmin/churches/{$church->id}/users/{$user->id}/role",['church_role_id'=>$roleId])->assertOk()->assertJsonPath('church_role_id',$roleId);
  $this->putJson("/api/superadmin/churches/{$other->id}/roles/$roleId",['name'=>'Cross church','revision'=>2,'permissions'=>$permissions])->assertNotFound();
  $this->postJson('/api/superadmin/churches/'.$church->id.'/roles',['name'=>'Bad','base_role'=>'superadmin'])->assertUnprocessable();
  $headers=['X-Church-Context'=>(string)$church->id,'X-Church-Role-Context'=>(string)$roleId];
  $this->getJson('/api/access',$headers)->assertOk()->assertJsonPath('permissions.calendar','read')->assertJsonPath('base_role','pastor');
  $this->getJson('/api/events?start=2026-01-01&end=2027-01-01',$headers)->assertOk();
  $this->postJson('/api/events/hold',[],$headers)->assertForbidden();
  $this->getJson('/api/members',$headers)->assertForbidden();
  $this->getJson('/api/access',array_replace($headers,['X-Church-Context'=>(string)$other->id]))->assertNotFound();
  Sanctum::actingAs($user->fresh());
  $this->getJson('/api/events?start=2026-01-01&end=2027-01-01')->assertOk();
  $this->getJson('/api/superadmin/churches/'.$church->id.'/roles')->assertForbidden();
  $this->putJson("/api/superadmin/churches/{$church->id}/roles/$roleId",['name'=>'Escalate','revision'=>2,'permissions'=>$this->permissions('edit')])->assertForbidden();
  $this->getJson('/api/access',$headers)->assertForbidden();
 }
 public function test_pastor_calendar_read_access_is_scoped_and_revocable_without_role_change(): void {
  $church=Church::create(['name'=>'One']);$other=Church::create(['name'=>'Two']);
  $dept=Department::create(['church_id'=>$church->id,'name'=>'Music']);$otherDept=Department::create(['church_id'=>$other->id,'name'=>'Other']);
  foreach([$dept,$otherDept] as $d) Event::create(['department_id'=>$d->id,'church_id'=>$d->church_id,'start_at'=>now()->addDay(),'end_at'=>now()->addDay()->addHour(),'status'=>'locked','title'=>$d->name,'created_by'=>User::factory()->create()->id,'updated_by'=>User::factory()->create()->id]);
  $pastor=User::factory()->create(['role'=>'pastor','church_id'=>$church->id]);Sanctum::actingAs($pastor);
  $this->getJson('/api/events?start=2026-01-01&end=2027-01-01')->assertForbidden();
  $role=$this->role($church,'pastor',['calendar'=>'read']);
  $this->getJson('/api/events?start=2026-01-01&end=2027-01-01')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title','Music');
  $event=Event::where('department_id',$dept->id)->first();
  $this->postJson('/api/events/hold',[])->assertForbidden();
  $this->patchJson('/api/events/'.$event->id,[])->assertForbidden();
  $this->postJson('/api/events/'.$event->id.'/review',[])->assertForbidden();
  $this->deleteJson('/api/events/'.$event->id)->assertForbidden();
  $role->update(['permissions'=>['calendar'=>'edit']]);
  // Edit access never turns a pastor into an event reviewer or department event author.
  $this->postJson('/api/events/hold',[])->assertForbidden();
  $this->postJson('/api/events/'.$event->id.'/review',[])->assertForbidden();
  $role->update(['permissions'=>['calendar'=>'none']]);
  $this->getJson('/api/events?start=2026-01-01&end=2027-01-01')->assertForbidden();
  $this->assertSame('pastor',$pastor->fresh()->role);
 }
 public function test_readonly_and_no_access_are_enforced_for_every_church_module(): void {
  $church=Church::create(['name'=>'One']);$pastor=User::factory()->create(['role'=>'pastor','church_id'=>$church->id]);
  $role=$this->role($church,'pastor',$this->permissions('read'));Sanctum::actingAs($pastor);
  foreach(['/api/events?start=2026-01-01&end=2027-01-01','/api/reports/events?start=2026-01-01&end=2027-01-01','/api/reports/objectives','/api/objectives','/api/budget','/api/inventory','/api/meetings','/api/meeting-points/report','/api/church-contacts','/api/visitor-form','/api/members','/api/secretary/departments','/api/secretary/users'] as $url)$this->getJson($url)->assertOk();
  foreach(['/api/events/hold','/api/reports/events/999/notes','/api/objectives','/api/budget/entries','/api/inventory','/api/meetings','/api/church-contacts','/api/visitor-form','/api/members','/api/secretary/departments','/api/secretary/users'] as $url){$response=$this->postJson($url,[]);$this->assertContains($response->status(),[403,404],$url);}
  $role->update(['permissions'=>$this->permissions()]);
  foreach(['/api/events?start=2026-01-01&end=2027-01-01','/api/reports/events?start=2026-01-01&end=2027-01-01','/api/reports/objectives','/api/objectives','/api/budget','/api/inventory','/api/meetings','/api/meeting-points/report','/api/church-contacts','/api/visitor-form','/api/members','/api/secretary/departments','/api/secretary/users'] as $url)$this->getJson($url)->assertForbidden();
 }
 public function test_report_access_does_not_unlock_calendar_mutations_or_role_manager(): void {
  $church=Church::create(['name'=>'One']);$user=User::factory()->create(['role'=>'secretary','church_id'=>$church->id]);
  $p=$this->permissions();$p['reports']='read';$this->role($church,'secretary',$p);Sanctum::actingAs($user);
  $this->getJson('/api/reports/events?start=2026-01-01&end=2027-01-01')->assertOk();$this->getJson('/api/reports/objectives')->assertOk();
  $this->getJson('/api/events?start=2026-01-01&end=2027-01-01')->assertForbidden();$this->postJson('/api/conference-events',[])->assertForbidden();
  $this->getJson('/api/superadmin/churches')->assertForbidden();
 }
}
