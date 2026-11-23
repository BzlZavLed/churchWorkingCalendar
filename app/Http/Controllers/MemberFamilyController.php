<?php
namespace App\Http\Controllers;
use App\Models\{Church,ChurchMember};
use App\Services\{MemberFamilies,ModuleAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MemberFamilyController extends Controller {
 private function church(Request $r): int {
  abort_unless($r->user()?->church_id && !$r->user()->isSuperAdmin() && app(ModuleAccess::class)->read($r->user(),'members'),403);
  return $r->user()->church_id;
 }
 public function index(Request $r,MemberFamilies $families){return response()->json(['families'=>$families->groups($this->church($r))])->header('Cache-Control','no-store');}
 public function move(Request $r,MemberFamilies $families){
  $church=$this->church($r);abort_unless($r->user()->hasCapability('members.manage'),403);
  $data=$r->validate(['member_ids'=>['required','array','min:1','max:1000'],'member_ids.*'=>['required','integer','distinct'],'mode'=>['required','in:existing,new,none'],'family_id'=>['nullable','required_if:mode,existing','string','max:500']]);
  return DB::transaction(function()use($church,$data,$families){
   Church::whereKey($church)->lockForUpdate()->firstOrFail();
   $members=ChurchMember::where('church_id',$church)->whereIn('id',$data['member_ids'])->lockForUpdate()->get();
   abort_unless($members->count()===count($data['member_ids']),404);
   $id=$families->resolve($church,$data['mode'],$data['family_id']??null);
   foreach($members as $member){$values=$member->data??[];$values['FamilyId']=$id;$member->data=$values;$member->save();}
   return ['family_id'=>$id,'updated'=>$members->count()];
  });
 }
}
