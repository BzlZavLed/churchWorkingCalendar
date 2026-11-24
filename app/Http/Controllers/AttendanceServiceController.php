<?php
namespace App\Http\Controllers;
use App\Models\{Church,AttendanceService,AttendanceSession};
use App\Services\{ModuleAccess,AttendanceSessions};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class AttendanceServiceController extends Controller {
 private function church(Request $r,bool $write=false): int {
  $u=$r->user();abort_unless($u?->church_id && !$u->isSuperAdmin() && app(ModuleAccess::class)->read($u,'greeting'),403);
  if($write)abort_unless($u->hasCapability('greeting.manage_services'),403);
  return $u->church_id;
 }
 public function index(Request $r) {
  $id=$this->church($r);
  return ['services'=>AttendanceService::where('church_id',$id)->orderBy('weekday')->orderBy('start_time')->get(),
   'sessions'=>AttendanceSession::where('church_id',$id)->orderByDesc('service_date')->orderBy('starts_at')->limit(100)->get(),
   'eligible'=>app(AttendanceSessions::class)->summary($id),'can_manage'=>$r->user()->hasCapability('greeting.manage_services')];
 }
 public function save(Request $r,?int $service=null) {
  $id=$this->church($r,true);
  $data=$r->validate(['name'=>'required|string|max:150','weekday'=>'required|integer|between:0,6','start_time'=>'required|date_format:H:i','end_time'=>'required|date_format:H:i|after:start_time','active'=>'required|boolean']);
  $model=$service?AttendanceService::where('church_id',$id)->findOrFail($service):new AttendanceService(['church_id'=>$id]);
  $model->fill($data)->save();return $model;
 }
 public function open(Request $r) {
  $id=$this->church($r,true);$data=$r->validate(['service_ids'=>'required|array|min:1|max:20','service_ids.*'=>'required|integer|distinct','timezone'=>'required|timezone','date'=>'required|date_format:Y-m-d']);
  return DB::transaction(function()use($r,$id,$data){
   Church::whereKey($id)->lockForUpdate()->firstOrFail();
   $today=now($data['timezone']);abort_unless($today->toDateString()===$data['date'],422,'Open attendance for today only.');
   abort_if(app(AttendanceSessions::class)->available($id)->isNotEmpty(),409,'Close the open attendance block before opening another.');
   $services=AttendanceService::where('church_id',$id)->whereIn('id',$data['service_ids'])->where('active',true)->get();
   abort_unless($services->count()===count($data['service_ids']),422,'Select active services in this church.');
   $batch=(string)Str::uuid();$created=[];
   foreach($services as $service){
    abort_unless($service->weekday===$today->dayOfWeek,422,'Selected service does not occur on this weekday.');
    $start=CarbonImmutable::parse($data['date'].' '.$service->start_time,$data['timezone'])->utc();$end=CarbonImmutable::parse($data['date'].' '.$service->end_time,$data['timezone'])->utc();
    abort_unless($end->gt(now()),422,'This service has already ended.');
    abort_if(AttendanceSession::where('attendance_service_id',$service->id)->where('service_date',$data['date'])->exists(),409,'This service has already been opened today. Use its reopen button.');
    $created[]=AttendanceSession::create(['church_id'=>$id,'attendance_service_id'=>$service->id,'batch_id'=>$batch,'name'=>$service->name,'service_date'=>$data['date'],'timezone'=>$data['timezone'],'starts_at'=>$start,'ends_at'=>$end,'opened_at'=>now(),'opened_by'=>$r->user()->id]);
   }
   return ['sessions'=>$created];
  });
 }
 public function status(Request $r,int $session) {
  $id=$this->church($r,true);$data=$r->validate(['open'=>'required|boolean']);
  return DB::transaction(function()use($id,$session,$data){
   Church::whereKey($id)->lockForUpdate()->firstOrFail();
   $record=AttendanceSession::where('church_id',$id)->findOrFail($session);
   if($data['open']){
    abort_unless(AttendanceSession::where('church_id',$id)->where('batch_id',$record->batch_id)->where('ends_at','>',now())->exists() && now($record->timezone)->toDateString()===$record->service_date,422,'Only today’s unexpired services can be reopened.');
    abort_if(app(AttendanceSessions::class)->available($id)->contains(fn($s)=>$s->batch_id!==$record->batch_id),409,'Another attendance block is open.');
   }
   // Close/reopen the block together so the automatic consecutive-service rule stays explicit.
   AttendanceSession::where('church_id',$id)->where('batch_id',$record->batch_id)->update(['closed_at'=>$data['open']?null:now()]);
   return ['open'=>$data['open']];
  });
 }
}
