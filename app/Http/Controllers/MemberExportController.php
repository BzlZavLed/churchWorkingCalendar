<?php
namespace App\Http\Controllers;
use App\Models\ChurchMember;
use App\Services\{MemberExports,MemberRecords,ModuleAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberExportController extends Controller
{
 private function church(Request $r): int {
  abort_unless($r->user()?->church_id && !$r->user()->isSuperAdmin() && app(ModuleAccess::class)->read($r->user(),'members'),403);
  return $r->user()->church_id;
 }
 private function presets(Request $r) {
  return DB::table('member_export_presets')->where('church_id',$this->church($r))->where('user_id',$r->user()->id);
 }
 public function index(Request $r,MemberExports $exports) {
  return response()->json(['columns'=>$exports->columns($this->church($r)),
   'presets'=>$this->presets($r)->orderBy('name')->get()->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'columns'=>json_decode($p->columns,true),'sort_by'=>$p->sort_by,'sort_direction'=>$p->sort_direction])])->header('Cache-Control','no-store');
 }
 public function save(Request $r,MemberExports $exports) {
  $church=$this->church($r);abort_unless($r->user()->hasCapability('members.manage'),403);
  $r->merge(['name'=>trim((string)$r->input('name'))]);
  $data=$r->validate($this->sortRules($r)+['name'=>['required','string','max:100'],'columns'=>['required','array','min:1'],
   'columns.*'=>['required','string','distinct',Rule::in(array_column($exports->columns($church),'id'))]]);
  $this->presets($r)->updateOrInsert(['church_id'=>$church,'user_id'=>$r->user()->id,'name'=>$data['name']],
   ['sort_by'=>$data['sort_by']??null,'sort_direction'=>$data['sort_direction']??'asc','columns'=>json_encode($data['columns']),'updated_at'=>now(),'created_at'=>now()]);
  return $this->index($r,$exports);
 }
 public function destroy(Request $r,int $preset) {
  abort_unless($r->user()->hasCapability('members.manage'),403);
  abort_unless($this->presets($r)->where('id',$preset)->delete(),404);
  return response()->noContent();
 }
 private function sortRules(Request $r): array {
  return ['sort_by'=>['nullable','string',Rule::in(is_array($r->input('columns'))?$r->input('columns'):[])],
   'sort_direction'=>['sometimes',Rule::in(['asc','desc'])]];
 }
 public function download(Request $r,MemberExports $exports) {
  $church=$this->church($r);$available=collect($exports->columns($church))->keyBy('id');
  $data=$r->validate($this->sortRules($r)+['columns'=>['required','array','min:1'],'columns.*'=>['required','string','distinct',Rule::in($available->keys()->all())]]);
  $fields=app(MemberRecords::class)->current($church)->fields;
  return response()->streamDownload(function()use($church,$data,$available,$exports,$fields){
   $stream=fopen('php://output','w');fwrite($stream,"\xEF\xBB\xBF");
   fputcsv($stream,array_map(fn($id)=>preg_replace('/^([=+@\-])/u',"'$1",$available[$id]['label']),$data['columns']),',','"','');
   $members=ChurchMember::where('church_id',$church)->orderBy('name')->get();
   if(!empty($data['sort_by'])) {
    $column=$data['sort_by'];$direction=($data['sort_direction']??'asc')==='desc'?-1:1;
    $members=$members->sort(function($a,$b)use($exports,$column,$fields,$direction){
     $left=$exports->rawValue($a,$column,$fields);$right=$exports->rawValue($b,$column,$fields);
     // Missing values always go last, including descending exports.
     $emptyLeft=$left===null||$left==='';$emptyRight=$right===null||$right==='';
     if($emptyLeft!==$emptyRight)return $emptyLeft?1:-1;
     $comparison=strnatcasecmp(\Illuminate\Support\Str::ascii((string)$left),\Illuminate\Support\Str::ascii((string)$right));
     return $comparison ? $comparison*$direction : (strnatcasecmp($a->name??'',$b->name??'') ?: $a->id<=>$b->id);
    });
   }
   foreach($members as $member)
    fputcsv($stream,array_map(fn($id)=>$exports->value($member,$id,$fields),$data['columns']),',','"','');
   fclose($stream);
  },'miembros-'.now()->format('Y-m-d').'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store']);
 }
}
