<?php
namespace App\Services\WhatsApp;
use App\Models\{User,WhatsAppAccount,WhatsAppMessage};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class Provisioning {
 public function apply(Request $request,User $user): void {
  $data=$request->validate(['whatsapp_phone'=>['sometimes','nullable','string','regex:/^\+[1-9][0-9]{6,14}$/D'],'whatsapp_assistant_enabled'=>['sometimes','boolean']]);
  if(!$data)return;
  $account=WhatsAppAccount::firstOrCreate(['user_id'=>$user->id]);
  $account=WhatsAppAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
  if(array_key_exists('whatsapp_phone',$data)) {
   $phone=$data['whatsapp_phone']?ltrim($data['whatsapp_phone'],'+'):null;$hash=$phone?WhatsAppAccount::senderHash($phone):null;
   if($hash && WhatsAppAccount::where('identity_hash',$hash)->where('id','!=',$account->id)->exists()) throw ValidationException::withMessages(['whatsapp_phone'=>['This WhatsApp number is already assigned to another account.']]);
   if($hash!==$account->identity_hash){
    $account->generation++;$account->sender=null;$account->sender_hash=null;$account->state=null;$account->link_hash=null;$account->link_expires_at=null;
    WhatsAppMessage::where('account_id',$account->id)->where('status','pending')->update(['status'=>'ignored','body'=>null]);
   }
   $account->identity_hash=$hash;$account->registered_sender=$phone;
  }
  if(array_key_exists('whatsapp_assistant_enabled',$data)) {
   $user->forceFill(['whatsapp_assistant_enabled'=>$data['whatsapp_assistant_enabled']])->save();
   if(!$data['whatsapp_assistant_enabled']) {$account->generation++;$account->state=null;$account->link_hash=null;$account->link_expires_at=null;WhatsAppMessage::where('account_id',$account->id)->where('status','pending')->update(['status'=>'ignored','body'=>null]);}
  }
  try{$account->save();}catch(\Illuminate\Database\UniqueConstraintViolationException){throw ValidationException::withMessages(['whatsapp_phone'=>['This WhatsApp number is already assigned to another account.']]);}
 }
 public function fields(User $user): array {
  $account=$user->relationLoaded('whatsappAccount')?$user->whatsappAccount:$user->whatsappAccount()->first();
  $phone=$account?->registered_sender ?: $account?->sender;
  return ['whatsapp_phone'=>$phone?'+'.$phone:null,'whatsapp_linked'=>(bool)$account?->sender_hash];
 }
}
