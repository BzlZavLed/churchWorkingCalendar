<?php
namespace App\Services;
use App\Models\Church;
use Illuminate\Support\Facades\DB;
class ChurchIntegrations {
 public function whatsapp(?int $id): bool { return $id && (bool) Church::whereKey($id)->where('clerk_whatsapp_enabled',true)->value('whatsapp_enabled'); }
 public function ai(?int $id): bool { return $id && (bool) Church::whereKey($id)->where('clerk_ai_enabled',true)->value('ai_enabled'); }
 public function limit(int $id,string $field,int $fallback): int { return min($fallback,(int)(Church::whereKey($id)->value($field) ?? $fallback)); }
 public function record(int $church,string $feature,array $usage): void {
  $key=['church_id'=>$church,'month'=>now()->format('Y-m'),'feature'=>$feature];
  DB::transaction(function()use($key,$usage){
   DB::table('integration_usage')->insertOrIgnore($key);
   DB::table('integration_usage')->where($key)->incrementEach(['input_tokens'=>max(0,(int)($usage['input_tokens']??0)),'output_tokens'=>max(0,(int)($usage['output_tokens']??0)),'responses'=>1]);
  });
 }
}
