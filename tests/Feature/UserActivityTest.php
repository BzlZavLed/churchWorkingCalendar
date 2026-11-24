<?php
namespace Tests\Feature;
use App\Models\{AuditLog,Church,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class UserActivityTest extends TestCase {
 use RefreshDatabase;
 public function test_clerk_sees_only_verified_church_records_and_no_raw_payload():void {
  $church=Church::create(['name'=>'One']);$other=Church::create(['name'=>'Two']);
  $clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);$user=User::factory()->create(['church_id'=>$church->id,'role'=>'member']);$foreign=User::factory()->create(['church_id'=>$other->id,'role'=>'member']);
  foreach([['church_id'=>$church->id],['church_id'=>$other->id],[]] as $meta) AuditLog::create(['user_id'=>$user->id,'action'=>'update','entity_type'=>'members','entity_id'=>'42','method'=>'PATCH','path'=>'/api/members/42','metadata'=>$meta+['payload'=>['secret'=>'private']],'created_at'=>now()]);
  Sanctum::actingAs($clerk);$this->getJson('/api/secretary/users/'.$user->id.'/activity')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.module','members')->assertJsonMissingPath('data.0.metadata');
  $this->getJson('/api/secretary/users/'.$foreign->id.'/activity')->assertNotFound();
  Sanctum::actingAs($user);$this->getJson('/api/secretary/users/'.$user->id.'/activity')->assertForbidden();
  Sanctum::actingAs(User::factory()->create(['role'=>'superadmin','church_id'=>null]));$this->getJson('/api/superadmin/users/'.$user->id.'/activity')->assertOk()->assertJsonCount(3,'data')->assertJsonMissingPath('data.0.metadata');
 }
 public function test_new_audit_log_captures_church():void {
  $church=Church::create(['name'=>'One']);$clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($clerk);
  $this->postJson('/api/secretary/departments',['name'=>'Music'])->assertCreated();
  $this->getJson('/api/secretary/users/'.$clerk->id.'/activity')->assertOk()->assertJsonPath('data.0.module','departments');
 }
 public function test_page_views_are_scoped_allowlisted_and_recorded_once_without_secrets():void {
  $church=Church::create(['name'=>'One']);$other=Church::create(['name'=>'Two']);$member=User::factory()->create(['church_id'=>$church->id,'role'=>'member']);$clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);$foreignClerk=User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']);
  Sanctum::actingAs($member);
  $this->postJson('/api/activity/page-view',['path'=>'/calendar'])->assertNoContent();
  $this->postJson('/api/activity/page-view',['path'=>'/calendar?token=secret'])->assertUnprocessable();
  $this->postJson('/api/activity/page-view',['path'=>'/reset-password'])->assertUnprocessable();
  $this->postJson('/api/activity/page-view',['path'=>'/superadmin/users'])->assertForbidden();
  $this->postJson('/api/activity/page-view',['path'=>'/members/123'])->assertForbidden();
  $this->assertSame(1,AuditLog::where('user_id',$member->id)->where('action','page_view')->count());
  Sanctum::actingAs($clerk);
  $this->getJson('/api/secretary/users/'.$member->id.'/activity?module=calendar')->assertOk()->assertJsonPath('data.0.action','page_view')->assertJsonPath('data.0.page','/calendar')->assertJsonMissingPath('data.0.metadata');
  Sanctum::actingAs($foreignClerk);$this->getJson('/api/secretary/users/'.$member->id.'/activity')->assertNotFound();
 }
 public function test_superadmin_context_page_views_are_attributed_to_actor_and_selected_church():void {
  $church=Church::create(['name'=>'One']);$admin=User::factory()->create(['role'=>'superadmin','church_id'=>null]);Sanctum::actingAs($admin);
  $this->postJson('/api/activity/page-view',['path'=>'/calendar'],['X-Church-Context'=>(string)$church->id])->assertNoContent();
  $log=AuditLog::where('user_id',$admin->id)->where('action','page_view')->firstOrFail();
  $this->assertSame($church->id,$log->metadata['church_id']);
 }
}
