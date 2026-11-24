<?php
namespace Tests\Feature;
use App\Models\{Church,User};
use App\Notifications\AccountPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class AccountInvitationTest extends TestCase {
 use RefreshDatabase;
 public function test_clerk_invitation_activates_only_with_single_use_token(): void {
  Notification::fake();$church=Church::create(['name'=>'Church']);Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $this->postJson('/api/secretary/users',['name'=>'Invited','email'=>'invited@example.test','role'=>'member'])->assertCreated()->assertJsonPath('pending_activation',true);
  $user=User::where('email','invited@example.test')->firstOrFail();
  Notification::assertSentTo($user,AccountPasswordLink::class,function($notification) use ($user){
   $payload=['email'=>$user->email,'token'=>$notification->token,'password'=>'new-password-123','password_confirmation'=>'new-password-123'];
   $this->postJson('/api/auth/login',['email'=>$user->email,'password'=>'new-password-123'])->assertUnprocessable();
   $this->postJson('/api/auth/reset-password',$payload)->assertNoContent();
   $this->postJson('/api/auth/reset-password',$payload)->assertUnprocessable();
   return true;
  });
  $this->assertFalse($user->fresh()->pending_activation);
  $this->postJson('/api/auth/login',['email'=>$user->email,'password'=>'new-password-123'])->assertOk();
 }
}
