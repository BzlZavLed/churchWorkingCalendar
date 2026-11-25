<?php
namespace App\Services;
use App\Models\{ChurchMember,MemberTemplate};
use Illuminate\Validation\ValidationException;
class MemberImportReview {
 public function analyze(MemberTemplate $template,array $rows): array {
  $format=MemberDateFormat::forChurch($template->church_id);
  $existing=ChurchMember::where('church_id',$template->church_id)->get();$bySource=$existing->filter(fn($m)=>filled($m->source_person_id))->keyBy('source_person_id');$seen=[];$review=[];$fileSources=[];
  foreach($rows as $row){$id=trim((string)($row['values']['PersonId']??''));if($id!=='')$fileSources[$id][]=$row['line'];}
  foreach($rows as $row){
   $values=array_replace(array_fill_keys(MemberDirectorySchema::HEADERS,''),$row['values']);$custom=[];
   foreach($template->fields as $field){
    if(!array_key_exists($field['id'],$values))continue;$value=trim((string)$values[$field['id']]);
    if($field['type']==='checkbox' && $value!=='')$value=match(strtolower($value)){'true','1','yes','y','si','sí','s'=>true,'false','0','no','n'=>false,default=>$value};
    if($field['type']==='dob' && $value!=='') {
     $value=MemberDateFormat::normalize($value,$format,fromImport:true)??'invalid';
    }
    $custom[$field['id']]=$value===''?null:$value;
   }
   $phone=collect(['MobilePhone','FamilyPhone','WorkPhone'])->map(fn($k)=>trim((string)($values[$k]??'')))->first(fn($v)=>$v!=='')??'';
   $input=['given_name'=>trim($values['FirstName']??''),'first_surname'=>trim($values['LastName']??''),'email'=>trim($values['Email']??''),'phone'=>$phone,'data'=>$custom];
   $errors=[];$payload=null;$missing=[];$canAuthorize=false;
   try{$payload=app(MemberRecords::class)->validate($template,$input);}catch(ValidationException $e){
    foreach($e->errors() as $key=>$messages){$column=match($key){'given_name','name'=>'FirstName','first_surname'=>'LastName','email'=>'Email','phone'=>'MobilePhone',default=>preg_replace('/^data\./','',$key)};$errors[$column]=$messages;}
    try{$payload=app(MemberRecords::class)->validate($template,$input,['name','email','phone','dob']);$missing=$payload['missing_fields'];$canAuthorize=(bool)$missing;}catch(ValidationException){$payload=null;}
   }
   $source=filled($custom['PersonId']??null)?$custom['PersonId']:null;
   if($source!==null){if(isset($seen[$source])){$errors['PersonId']=['Duplicate PersonId in this file.'];$canAuthorize=false;$payload=null;}$seen[$source]=true;}
   $matches=collect();
   if($source!==null && isset($bySource[$source]))$matches=collect([$bySource[$source]]);
   elseif($payload && filled($payload['name']??null) && filled($payload['data']['BornOn']??null))$matches=$existing->filter(fn($m)=>mb_strtolower(trim($m->name??''))===mb_strtolower(trim($payload['name'])) && MemberDateFormat::normalize($m->data['BornOn']??null,$format)===$payload['data']['BornOn']);
   $matches=$matches->map(fn($m)=>['id'=>$m->id,'name'=>$m->name,'dob'=>$m->data['BornOn']??null,'match'=>$source!==null && (string)$m->source_person_id===(string)$source?'person_id':'name_dob'])->values()->all();
   $action=($row['duplicate_action']??'skip')==='update'?'update':'skip';$target=isset($row['duplicate_target'])?(int)$row['duplicate_target']:null;
   if($matches && $action==='update' && !collect($matches)->contains('id',$target)){$errors['PersonId']=['Select a matching member to update.'];$canAuthorize=false;}
   $authorized=$canAuthorize && ($row['authorize_missing']??false);
   if($payload){$payload['source_person_id']=$source;}
   $fileDuplicates=$source!==null && count($fileSources[$source]??[])>1?array_values(array_diff($fileSources[$source],[$row['line']])):[];
   $review[]=['line'=>$row['line'],'values'=>$values,'errors'=>$errors,'missing_fields'=>$missing,'can_authorize'=>$canAuthorize,'authorize_missing'=>(bool)$authorized,'duplicate_matches'=>$matches,'duplicate_file_lines'=>$fileDuplicates,'duplicate_action'=>$matches?$action:'new','duplicate_target'=>$target,'eligible'=>!$errors||$authorized,'payload'=>$payload];
  }
  return $review;
 }
 public function response(array $rows): array {
  $clean=array_map(function($r){unset($r['payload']);return $r;},$rows);
  return ['rows'=>$clean,'headers'=>MemberDirectorySchema::HEADERS,'valid'=>!collect($rows)->contains(fn($r)=>(bool)$r['errors']),'count'=>count($rows),'invalid_count'=>collect($rows)->filter(fn($r)=>(bool)$r['errors'])->count(),'eligible_count'=>collect($rows)->where('eligible',true)->count()];
 }
}
