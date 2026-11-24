<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AccountPasswordController extends Controller {
 public function requestLink(Request $request) {
  $data=$request->validate(['email'=>['required','email','max:255']]);
  $user=User::where('email',$data['email'])->first();
  if($user)try { app(\App\Services\AccountEmailSender::class)->send($user,null); } catch (\Throwable $e) { report($e); }
  return response()->noContent();
 }
 public function reset(Request $request) {
  $data=$request->validate(['email'=>['required','email'],'token'=>['required','string'],'password'=>['required','string','min:8','confirmed']]);
  $status=Password::reset($data,function(User $user,string $password){
   $user->forceFill(['password'=>$password,'pending_activation'=>false,'email_verified_at'=>$user->email_verified_at??now(),'remember_token'=>Str::random(60)])->save();
   $user->tokens()->delete();
  });
  if($status!==Password::PASSWORD_RESET) throw ValidationException::withMessages(['email'=>['El enlace es inválido o ha expirado. Solicita uno nuevo.']]);
  return response()->noContent();
 }
 public function sendForUser(Request $request, User $user) {
  abort_unless($request->user()->hasCapability('users.manage') && $request->user()->church_id===$user->church_id,404);
  $status=app(\App\Services\AccountEmailSender::class)->send($user,$request->user()->id);
  if(!in_array($status,['accepted','simulated'],true)) throw ValidationException::withMessages(['email'=>['No se pudo enviar el correo. Intenta de nuevo más tarde.']]);
  return response()->json(['status'=>$status]);
 }
}
