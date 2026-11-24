<?php
namespace Tests\Feature;
use App\Models\{Church,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class EmailActivityTest extends TestCase {
 use RefreshDatabase;
 public function test_only_superadmin_can_view_invitation_and_reset_activity():void {
  Notification::fake();$church=Church::create(['name'=>'Church']);$clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($clerk);
  $this->postJson('/api/secretary/users',['name'=>'Invited','email'=>'invited@example.test','role'=>'member'])->assertCreated()->assertJsonPath('invitation_status','simulated');
  $this->getJson('/api/superadmin/email-activity')->assertForbidden();
  $this->assertDatabaseHas('email_activity',['actor_id'=>$clerk->id,'recipient_email'=>'invited@example.test','kind'=>'invitation','status'=>'simulated']);
  Sanctum::actingAs(User::factory()->create(['role'=>'superadmin','church_id'=>null]));
  $this->getJson('/api/superadmin/email-activity')->assertOk()->assertJsonPath('data.0.actor_name',$clerk->name)->assertJsonPath('data.0.church_name','Church')->assertJsonMissingPath('data.0.token');
 }
}
