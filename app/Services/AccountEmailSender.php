<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
class AccountEmailSender {
 public function send(User $user, ?int $actorId): string {
  $status='failed';
  try {
   $result=Password::sendResetLink(['email'=>$user->email]);
   $status=$result===Password::RESET_LINK_SENT?(in_array(config('mail.default'),['array','log'],true)?'simulated':'accepted'):($result===Password::RESET_THROTTLED?'throttled':'failed');
   return $status;
  } finally {
   DB::table('email_activity')->insert(['church_id'=>$user->church_id,'actor_id'=>$actorId,'recipient_id'=>$user->id,'recipient_email'=>$user->email,'kind'=>$user->pending_activation?'invitation':'password_reset','status'=>$status,'created_at'=>now()]);
  }
 }
}
