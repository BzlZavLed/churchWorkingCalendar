<?php
namespace App\Services;
use App\Models\AttendanceSession;
class AttendanceSessions {
 public function available(int $church) {
  return AttendanceSession::where('church_id',$church)->whereNull('closed_at')->where('opened_at','<=',now())->where('ends_at','>',now())->orderBy('starts_at')->orderBy('id')->get();
 }
 public function eligible(int $church) {
  $sessions=$this->available($church);
  // In a jointly opened block, arriving before the next start counts for current and later services.
  return $sessions->groupBy('batch_id')->flatMap(function($group){
   $current=$group->filter(fn($s)=>$s->starts_at->lte(now()))->max('starts_at');
   return $current?$group->filter(fn($s)=>$s->starts_at->gte($current)):$group;
  })->values();
 }
 public function summary(int $church): array {
  return $this->eligible($church)->map(fn($s)=>['id'=>$s->id,'name'=>$s->name,'date'=>$s->service_date,'time'=>$s->starts_at->setTimezone($s->timezone)->format('H:i'),'timezone'=>$s->timezone])->all();
 }
}
