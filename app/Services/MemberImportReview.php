<?php
namespace App\Services;
use App\Models\{ChurchMember,MemberTemplate};
use Illuminate\Validation\ValidationException;
class MemberImportReview {
 public function analyze(MemberTemplate $template,array $rows): array {
  $existing=ChurchMember::where('church_id',$template->church_id)->whereNotNull('source_person_id')->pluck('source_person_id')->flip();$seen=[];$review=[];
  foreach($rows as $row){
   $values=array_replace(array_fill_keys(MemberDirectorySchema::HEADERS,''),$row['values']);$custom=[];
   foreach($template->fields as $field){
    if(!array_key_exists($field['id'],$values))continue;$value=trim((string)$values[$field['id']]);
    if($field['type']==='checkbox' && $value!=='')$value=match(strtolower($value)){'true','1','yes','y','si','sí','s'=>true,'false','0','no','n'=>false,default=>$value};
    if($field['id']==='BornOn' && preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~D',$value,$parts))$value=sprintf('%04d-%02d-%02d',$parts[3],$parts[1],$parts[2]);
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
   if($source!==null){if(isset($existing[$source])||isset($seen[$source])){$errors['PersonId']=['Duplicate PersonId in this church or file.'];$canAuthorize=false;$payload=null;}$seen[$source]=true;}
   $authorized=$canAuthorize && ($row['authorize_missing']??false);
   if($payload){$payload['source_person_id']=$source;}
   $review[]=['line'=>$row['line'],'values'=>$values,'errors'=>$errors,'missing_fields'=>$missing,'can_authorize'=>$canAuthorize,'authorize_missing'=>(bool)$authorized,'eligible'=>!$errors||$authorized,'payload'=>$payload];
  }
  return $review;
 }
 public function response(array $rows): array {
  $clean=array_map(function($r){unset($r['payload']);return $r;},$rows);
  return ['rows'=>$clean,'headers'=>MemberDirectorySchema::HEADERS,'valid'=>!collect($rows)->contains(fn($r)=>(bool)$r['errors']),'count'=>count($rows),'invalid_count'=>collect($rows)->filter(fn($r)=>(bool)$r['errors'])->count(),'eligible_count'=>collect($rows)->where('eligible',true)->count()];
 }
}
