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
}
