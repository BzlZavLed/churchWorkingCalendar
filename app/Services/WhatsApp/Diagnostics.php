<?php
namespace App\Services\WhatsApp;

use App\Models\{Church, WhatsAppMessage};
use Illuminate\Support\Facades\{Cache, Http};

class Diagnostics
{
    public function snapshot(Church $church): array
    {
        $settings = [];
        foreach ([
            'WHATSAPP_CHURCH_ID'=>'whatsapp.church_id', 'WHATSAPP_PHONE_NUMBER_ID'=>'whatsapp.phone_number_id',
            'WHATSAPP_BUSINESS_ACCOUNT_ID'=>'whatsapp.business_account_id','WHATSAPP_ACCESS_TOKEN'=>'whatsapp.access_token',
            'WHATSAPP_APP_SECRET'=>'whatsapp.app_secret','WHATSAPP_VERIFY_TOKEN'=>'whatsapp.verify_token',
            'WHATSAPP_GRAPH_VERSION'=>'whatsapp.graph_version','OPENAI_API_KEY'=>'assistant.openai_api_key',
            'OPENAI_MODEL'=>'assistant.openai_model',
        ] as $name=>$key) $settings[$name] = filled(config($key));
        $query = WhatsAppMessage::whereHas('account.user',fn($q)=>$q->where('church_id',$church->id));
        $latest = (clone $query)->latest('id')->limit(20)->with('account.user:id,name,church_id')->get();
        $processed = (clone $query)->whereIn('status',['processed','failed','rejected','reply_failed'])->max('updated_at');
        return [
            'checked_at'=>now()->toIso8601String(), 'configuration'=>$settings,
            'switches'=>[
                'server'=>(bool)config('whatsapp.enabled'),
                'pilot'=>(string)config('whatsapp.church_id')===(string)$church->id,
                'superadmin_whatsapp'=>(bool)$church->whatsapp_enabled,
                'clerk_whatsapp'=>(bool)$church->clerk_whatsapp_enabled,
                'superadmin_ai'=>(bool)$church->ai_enabled,
                'clerk_ai'=>(bool)$church->clerk_ai_enabled,
            ],
            'pending'=>(clone $query)->where('status','pending')->count(),
            'pending_over_two_minutes'=>(clone $query)->where('status','pending')->where('created_at','<',now()->subMinutes(2))->count(),
            'processing_over_two_minutes'=>(clone $query)->where('status','processing')->where('updated_at','<',now()->subMinutes(2))->count(),
            'last_worker_activity'=>$processed ? \Carbon\Carbon::parse($processed)->toIso8601String() : null,
            'messages'=>$latest->map(fn($m)=>[
                'id'=>$m->id,'user_name'=>$m->account?->user?->name,'status'=>$m->status,
                'error_code'=>$m->error_code,'reply_status'=>$m->reply_status,
                'created_at'=>$m->created_at,'updated_at'=>$m->updated_at,
            ]),
        ];
    }

    public function checkProviders(): array
    {
        $identity = hash('sha256',json_encode([config('whatsapp.access_token'),config('whatsapp.phone_number_id'),config('whatsapp.graph_version'),config('assistant.openai_api_key'),config('assistant.openai_model')]));
        return Cache::remember('whatsapp-diagnostics:'.$identity,now()->addMinute(),function(){
            $version=(string)config('whatsapp.graph_version');
            $phone=(string)config('whatsapp.phone_number_id');
            $meta = preg_match('/^v[0-9]+\.[0-9]+$/D',$version) && ctype_digit($phone) && filled(config('whatsapp.access_token'))
                ? $this->probe("https://graph.facebook.com/$version/$phone",config('whatsapp.access_token'),['fields'=>'id'],'meta')
                : ['status'=>'not_configured'];
            $model=(string)config('assistant.openai_model');
            $openai = $model && filled(config('assistant.openai_api_key'))
                ? $this->probe('https://api.openai.com/v1/models/'.rawurlencode($model),config('assistant.openai_api_key'),[],'openai')
                : ['status'=>'not_configured'];
            return ['checked_at'=>now()->toIso8601String(),'meta'=>$meta,'openai'=>$openai];
        });
    }

    private function probe(string $url,string $token,array $params,string $provider): array
    {
        try {
            $r=Http::withToken($token)->connectTimeout(3)->timeout(8)->get($url,$params);
            $code=$r->json('error.code');
            $result=['status'=>$r->successful()?'reachable':'rejected','http_status'=>$r->status()];
            if ($provider==='meta' && is_numeric($code)) $result['code']='meta_error_'.(int)$code;
            if ($provider==='openai') $result['code']=$r->successful()?null:'openai_http_'.$r->status();
            return $result;
        } catch (\Throwable) { return ['status'=>'connection_failed']; }
    }
}
