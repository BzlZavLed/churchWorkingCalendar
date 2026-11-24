<?php
namespace App\Http\Controllers;
use App\Models\{AuditLog,User};
use Illuminate\Http\Request;
class UserActivityController extends Controller {
 private const MODULES = [
  'events'=>'calendar','conference-events'=>'calendar','calendar'=>'calendar','notes'=>'calendar','objectives'=>'objectives',
  'meetings'=>'meetings','meeting-points'=>'meeting_points','meeting-point-notes'=>'meeting_points','members'=>'members',
  'church-contacts'=>'visitors','visitor-form'=>'visitors','attendance'=>'attendance','budget'=>'budget','inventory'=>'inventory',
  'committees'=>'committees','departments'=>'departments','users'=>'users','auth'=>'account',
 ];
 public function clerk(Request $r,User $user) {
  abort_unless($r->user()?->hasCapability('users.manage') && $r->user()->church_id && $user->church_id===$r->user()->church_id,404);
  return $this->history($r,$user,$r->user()->church_id);
 }
 public function superadmin(Request $r,User $user) {
  abort_unless($r->user()?->isSuperAdmin(),403);
  return $this->history($r,$user,null);
 }
 private function history(Request $r,User $user,?int $church) {
  $data=$r->validate(['from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d','after_or_equal:from'],'module'=>['nullable','string','in:calendar,objectives,meetings,meeting_points,members,visitors,attendance,budget,inventory,committees,departments,users,account,other']]);
  $query=AuditLog::where('user_id',$user->id)->when($church,fn($q)=>$q->where(fn($q)=>$q->where('metadata->church_id',$church)->orWhere('metadata->viewing_context->church_id',$church)))
   ->when($data['from']??null,fn($q,$v)=>$q->whereDate('created_at','>=',$v))->when($data['to']??null,fn($q,$v)=>$q->whereDate('created_at','<=',$v));
  if(isset($data['module'])) { $types=array_keys(array_filter(self::MODULES,fn($m)=>$m===$data['module']));if($data['module']==='other')$query->where(fn($q)=>$q->whereNull('entity_type')->orWhereNotIn('entity_type',array_keys(self::MODULES)));else $query->whereIn('entity_type',$types); }
  $result=$query->orderByDesc('id')->paginate(25,['id','action','entity_type','entity_id','created_at']);
  $result->getCollection()->transform(fn($log)=>['id'=>$log->id,'at'=>$log->created_at,'module'=>self::MODULES[$log->entity_type]??'other','action'=>in_array($log->action,['create','update','delete','login','logout','register','password_reset'],true)?$log->action:'other','item_id'=>ctype_digit((string)$log->entity_id)?$log->entity_id:null]);
  return response()->json($result)->header('Cache-Control','no-store');
 }
}
