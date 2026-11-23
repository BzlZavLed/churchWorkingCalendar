<?php
namespace App\Services;
use App\Models\ChurchMember;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class MemberFamilies {
 public function groups(int $church): array {
  return ChurchMember::where('church_id',$church)->orderBy('name')->get()->filter(fn($m)=>filled($m->data['FamilyId']??null))
   ->groupBy(fn($m)=>(string)$m->data['FamilyId'])->map(fn($members,$id)=>['id'=>(string)$id,'count'=>$members->count(),'names'=>$members->pluck('name')->values()->all()])->values()->all();
 }
 public function resolve(int $church,string $mode,?string $id): ?string {
  if($mode==='none')return null;
  if($mode==='new')return 'F-'.Str::uuid();
  if(!collect($this->groups($church))->contains(fn($g)=>$g['id']===$id))
   throw ValidationException::withMessages(['family_id'=>['Choose an existing family in this church.']]);
  return $id;
 }
}
