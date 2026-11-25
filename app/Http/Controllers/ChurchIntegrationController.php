<?php
namespace App\Http\Controllers;
use App\Models\{Church,WhatsAppAccount,WhatsAppMessage};
use App\Services\ChurchIntegrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ChurchIntegrationController extends Controller {
 private function clerkChurch(Request $request): Church {
  abort_unless($request->user()?->isSecretary() && $request->user()->church_id,403);
  return Church::findOrFail($request->user()->church_id);
 }
 public function diagnostics(Request $request) {
  return response()->json(app(\App\Services\WhatsApp\Diagnostics::class)->snapshot($this->clerkChurch($request)))->header('Cache-Control','no-store');
 }
 public function checkProviders(Request $request) {
  $this->clerkChurch($request);
  return response()->json(app(\App\Services\WhatsApp\Diagnostics::class)->checkProviders())->header('Cache-Control','no-store');
 }
 public function clerkShow(Request $request) {
  $church=$this->clerkChurch($request);
  $data=$this->show($request,$church);
  foreach($data['usage'] as &$entry) { unset($entry['global_used'],$entry['global_limit']); }
  unset($entry);
  $data['church_name']=$church->name;
  $data['logo_url']=$church->visitorLogoUrl();
  $data['organization_code']=$church->organization_code;
  $data['member_date_format']=$church->member_date_format;
  $data['admin_whatsapp_enabled']=$data['whatsapp_enabled'];
  $data['admin_ai_enabled']=$data['ai_enabled'];
  $data['whatsapp_enabled']=(bool)$church->clerk_whatsapp_enabled;
  $data['ai_enabled']=(bool)$church->clerk_ai_enabled;
  return $data;
 }
 public function clerkUpdate(Request $request) {
  $church=$this->clerkChurch($request);
  $data=$request->validate(['member_date_format'=>'sometimes|required|in:mdy,dmy,ydm','organization_code'=>'sometimes|nullable|string|max:100','whatsapp_enabled'=>'required|boolean','ai_enabled'=>'required|boolean',
   'limits'=>'prohibited','whatsapp_ai_limit'=>'prohibited','whatsapp_replies_limit'=>'prohibited',
   'whatsapp_inbox_limit'=>'prohibited','whatsapp_daily_limit'=>'prohibited','birthday_ai_limit'=>'prohibited',
   'church_id'=>'prohibited','admin_whatsapp_enabled'=>'prohibited','admin_ai_enabled'=>'prohibited']);
  DB::transaction(function()use($church,$data){
   $church=Church::whereKey($church->id)->lockForUpdate()->firstOrFail();
   if($church->clerk_whatsapp_enabled && !$data['whatsapp_enabled']) {
    $ids=WhatsAppAccount::whereHas('user',fn($q)=>$q->where('church_id',$church->id))->pluck('id');
    WhatsAppAccount::whereIn('id',$ids)->increment('generation');
    WhatsAppAccount::whereIn('id',$ids)->update(['state'=>null,'link_hash'=>null,'link_expires_at'=>null]);
    WhatsAppMessage::whereIn('account_id',$ids)->where('status','pending')->update(['status'=>'ignored','body'=>null]);
   }
   if(array_key_exists('organization_code',$data))$church->organization_code=filled($data['organization_code'])?trim($data['organization_code']):null;
   if (isset($data['member_date_format'])) $church->member_date_format=$data['member_date_format'];
   $church->forceFill(['clerk_whatsapp_enabled'=>$data['whatsapp_enabled'],'clerk_ai_enabled'=>$data['ai_enabled']])->save();
  });
  return $this->clerkShow($request);
 }
 public function show(Request $request,Church $church) {
  $input=$request->validate(['month'=>['sometimes','date_format:Y-m']]);$month=$input['month']??now()->format('Y-m');
  $service=app(ChurchIntegrations::class);$limits=[];$usage=[];
  foreach(['ai','replies','inbox'] as $kind){
   $limits['whatsapp_'.$kind.'_limit']=$service->limit($church->id,'whatsapp_'.$kind.'_limit',(int)config('whatsapp.monthly_'.$kind.'_limit'));
   $usage[$kind]=['used'=>(int)DB::table('whatsapp_usage')->where('bucket',"church:{$church->id}:$kind:$month")->value('used'),'global_used'=>(int)DB::table('whatsapp_usage')->where('bucket',"global:$kind:$month")->value('used'),'global_limit'=>(int)config('whatsapp.monthly_'.$kind.'_limit')];
  }
  $limits['whatsapp_daily_limit']=$service->limit($church->id,'whatsapp_daily_limit',(int)config('whatsapp.daily_user_limit'));
  $limits['birthday_ai_limit']=$service->limit($church->id,'birthday_ai_limit',(int)config('assistant.birthday_monthly_limit'));
  $usage['birthdays']=['used'=>(int)DB::table('whatsapp_usage')->where('bucket',"birthdays:month:{$church->id}:$month")->value('used')];
  return ['church_id'=>$church->id,'whatsapp_enabled'=>(bool)$church->whatsapp_enabled,'ai_enabled'=>(bool)$church->ai_enabled,'limits'=>$limits,'usage'=>$usage,'month'=>$month,
   'clerk_whatsapp_enabled'=>(bool)$church->clerk_whatsapp_enabled,'clerk_ai_enabled'=>(bool)$church->clerk_ai_enabled,
   'global_enabled'=>(bool)config('whatsapp.enabled'),'pilot'=>(string)$church->id===(string)config('whatsapp.church_id'),
   'openai_configured'=>(bool)(config('assistant.openai_api_key')&&config('assistant.openai_model')),'model'=>config('assistant.openai_model'),
   'tokens'=>DB::table('integration_usage')->where('church_id',$church->id)->where('month',$month)->get(['feature','input_tokens','output_tokens','responses']),
   'linked'=>WhatsAppAccount::whereHas('user',fn($q)=>$q->where('church_id',$church->id))->whereNotNull('sender_hash')->count(),
   'pending'=>WhatsAppAccount::whereHas('user',fn($q)=>$q->where('church_id',$church->id)->where('whatsapp_assistant_enabled',true))->whereNotNull('registered_sender')->whereNull('sender_hash')->count()];
 }
 public function providers(Request $request,Church $church) {
  $input=$request->validate(['month'=>['required','date_format:Y-m']]);
  return app(\App\Services\ProviderUsage::class)->report($input['month']);
 }
 public function update(Request $request,Church $church) {
  $rules=['whatsapp_enabled'=>'required|boolean','ai_enabled'=>'required|boolean'];
  foreach(['ai','replies','inbox'] as $kind)$rules['whatsapp_'.$kind.'_limit']='required|integer|min:0|max:'.(int)config('whatsapp.monthly_'.$kind.'_limit');
  $rules['whatsapp_daily_limit']='required|integer|min:0|max:'.(int)config('whatsapp.daily_user_limit');
  $rules['birthday_ai_limit']='required|integer|min:0|max:'.(int)config('assistant.birthday_monthly_limit');
  $data=$request->validate($rules);
  DB::transaction(function()use($church,$data){
   $church=Church::whereKey($church->id)->lockForUpdate()->firstOrFail();
   if($church->whatsapp_enabled && !$data['whatsapp_enabled']) {
    $ids=WhatsAppAccount::whereHas('user',fn($q)=>$q->where('church_id',$church->id))->pluck('id');
    WhatsAppAccount::whereIn('id',$ids)->increment('generation');
    WhatsAppAccount::whereIn('id',$ids)->update(['state'=>null,'link_hash'=>null,'link_expires_at'=>null]);
    WhatsAppMessage::whereIn('account_id',$ids)->where('status','pending')->update(['status'=>'ignored','body'=>null]);
   }
   $church->forceFill($data)->save();
  });
  return $this->show($request,$church->fresh());
 }
}
