<?php
namespace App\Http\Controllers;
use App\Models\{ChurchMember,Committee,Meeting};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class CommitteeController extends Controller {
 private function church(Request $request): int {abort_unless($request->user()->church_id,403,'Select a church work context.');return $request->user()->church_id;}
 public function index(Request $request){
  $church=$this->church($request);
  return ['minutes'=>Meeting::where('church_id',$church)->whereNotNull('end_at')->whereNotNull('minutes_number')->orderByDesc('minutes_number')->get(['id','minutes_number','meeting_date','summary_public']),'committees'=>Committee::where('church_id',$church)->with(['creator:id,name','members:id,name,email','reports.uploader:id,name'])->withCount('members')->orderByDesc('formation_voted_on')->orderByDesc('id')->get(),
   'members'=>ChurchMember::where('church_id',$church)->orderBy('name')->get(['id','name','email'])];
 }
 public function save(Request $request,?Committee $committee=null){
  $church=$this->church($request);abort_unless($request->user()->hasCapability('committees.manage'),403);
  if($committee)abort_unless($committee->church_id===$church,404);
  $data=$request->validate([
   'name'=>['required','string','max:255'],'objective'=>['required','string','max:10000'],
   'formation_voted_on'=>['required','date_format:Y-m-d','before_or_equal:today'],
   'formation_meeting_id'=>['nullable','integer',Rule::exists('meetings','id')->where('church_id',$church)->whereNotNull('end_at')->whereNotNull('minutes_number')],
   'minutes_number'=>['required_without:formation_meeting_id','nullable','string','max:100'],
   'member_ids'=>['required','array','min:1'],'member_ids.*'=>['required','integer','distinct',Rule::exists('church_members','id')->where('church_id',$church)],
   'revision'=>[$committee?'required':'sometimes','integer','min:1'],
  ]);
  $data['formation_meeting_id']=$data['formation_meeting_id'] ?? null;
  if($data['formation_meeting_id']) $data['minutes_number']=(string) Meeting::where('church_id',$church)->findOrFail($data['formation_meeting_id'])->minutes_number;
  return DB::transaction(function() use($request,$committee,$church,$data){
   $isNew=!$committee;
   if($committee){$committee=Committee::whereKey($committee->id)->lockForUpdate()->firstOrFail();abort_unless($committee->revision===$data['revision'],409,'Committee changed. Reload before saving.');}
   else $committee=new Committee(['church_id'=>$church,'created_by'=>$request->user()->id,'revision'=>0]);
   $committee->fill(collect($data)->only(['name','objective','formation_voted_on','minutes_number','formation_meeting_id'])->all());
   $committee->revision++;$committee->save();$committee->members()->sync($data['member_ids']);
   return response()->json($committee->load(['creator:id,name','members:id,name,email','reports.uploader:id,name'])->loadCount('members'),$isNew?201:200);
  });
 }
}
