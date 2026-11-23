<?php
namespace App\Http\Controllers;
use App\Models\{Church,ChurchMember,MemberImportDraft};
use App\Services\{MemberDirectorySchema,MemberImportReview,MemberRecords};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MemberImportDraftController extends Controller {
 private function church(Request $r): int {abort_unless($r->user()?->church_id && !$r->user()->isSuperAdmin() && $r->user()->hasCapability('members.manage'),403);return $r->user()->church_id;}
 private function draft(Request $r,int $id,bool $lock=false): MemberImportDraft {$q=MemberImportDraft::where('church_id',$this->church($r))->where('user_id',$r->user()->id)->whereKey($id);if($lock)$q->lockForUpdate();return $q->firstOrFail();}
 private function response(MemberImportDraft $draft): array {$rows=app(MemberImportReview::class)->analyze(app(MemberRecords::class)->current($draft->church_id),$draft->rows);return ['draft_id'=>$draft->id,'revision'=>$draft->revision,'updated_at'=>$draft->updated_at]+app(MemberImportReview::class)->response($rows);}
 public function index(Request $r){$church=$this->church($r);return response()->json(['drafts'=>MemberImportDraft::where('church_id',$church)->where('user_id',$r->user()->id)->latest('updated_at')->get()->filter(fn($d)=>count($d->rows)>0)->map(fn($d)=>['id'=>$d->id,'count'=>count($d->rows),'updated_at'=>$d->updated_at])->values()])->header('Cache-Control','no-store');}
 public function show(Request $r,int $draft){return response()->json($this->response($this->draft($r,$draft)))->header('Cache-Control','no-store');}
 public function save(Request $r,?int $draft=null){
  $church=$this->church($r);
  $data=$r->validate(['pending_only'=>['sometimes','boolean'],'revision'=>[$draft?'required':'nullable','integer','min:1'],'rows'=>['required','array','min:1','max:1000'],'rows.*'=>['array:line,values,authorize_missing'],'rows.*.line'=>['required','integer','min:2','distinct'],'rows.*.authorize_missing'=>['sometimes','boolean'],'rows.*.values'=>['required','array:'.implode(',',MemberDirectorySchema::HEADERS)],'rows.*.values.*'=>['nullable','string','max:500']]);
  return DB::transaction(function()use($r,$church,$data,$draft){
   Church::whereKey($church)->lockForUpdate()->firstOrFail();
   $model=$draft?$this->draft($r,$draft,true):new MemberImportDraft(['church_id'=>$church,'user_id'=>$r->user()->id,'revision'=>0]);
   if($draft)abort_unless($model->revision===$data['revision'],409,'This draft changed. Reopen the saved draft before editing.');
   // Revalidate eligibility; clients cannot waive malformed data or choose another church.
   $review=app(MemberImportReview::class)->analyze(app(MemberRecords::class)->current($church),$data['rows']);
   $savedRows=($data['pending_only']??false)?array_values(array_filter($review,fn($row)=>count($row['errors'])>0)):$review;
   $model->rows=array_map(fn($row)=>['line'=>$row['line'],'values'=>$row['values'],'authorize_missing'=>$row['authorize_missing']],$savedRows);$model->revision++;$model->save();
   // Keep all reviewed rows in the browser; only pending rows are persisted when requested.
   return response()->json(['draft_id'=>$model->id,'revision'=>$model->revision,'updated_at'=>$model->updated_at,'saved_count'=>count($savedRows)]+app(MemberImportReview::class)->response($review))->header('Cache-Control','no-store');
  });
 }
 public function destroy(Request $r,int $draft){
  $this->draft($r,$draft)->delete();
  return response()->noContent();
 }
 public function commit(Request $r,int $draft){
  $church=$this->church($r);$data=$r->validate(['revision'=>['required','integer','min:1']]);
  return DB::transaction(function()use($r,$church,$data,$draft){
   Church::whereKey($church)->lockForUpdate()->firstOrFail();$model=$this->draft($r,$draft,true);
   abort_unless($model->revision===$data['revision'],409,'This draft changed. Reopen it before importing.');
   $template=app(MemberRecords::class)->current($church);$review=app(MemberImportReview::class)->analyze($template,$model->rows);$remaining=[];$created=0;
   foreach($review as $row){
    if(!$row['eligible']){$remaining[]=['line'=>$row['line'],'values'=>$row['values'],'authorize_missing'=>false];continue;}
    $payload=$row['payload'];
    if($row['authorize_missing'])$payload['missing_data_authorization']=['fields'=>$row['missing_fields'],'user_id'=>$r->user()->id,'at'=>now()->toIso8601String()];
    ChurchMember::create($payload+['church_id'=>$church,'member_template_id'=>$template->id]);$created++;
   }
   $model->rows=$remaining;$model->revision++;$model->save();
   return response()->json(['created'=>$created,'remaining'=>count($remaining),'review'=>$this->response($model)])->header('Cache-Control','no-store');
  });
 }
}
