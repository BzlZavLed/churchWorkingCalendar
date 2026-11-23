<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::table('church_members',function(Blueprint $t){$t->json('missing_fields')->nullable();$t->json('missing_data_authorization')->nullable();});
  DB::table('church_members')->orderBy('id')->chunkById(100,function($members){foreach($members as $member){
   $data=json_decode($member->data,true)??[];$dob=$data['BornOn']??null;
   if(!$dob){$template=DB::table('member_templates')->find($member->member_template_id);foreach(json_decode($template->fields??'[]',true) as $field){if($field['type']==='dob' && !empty($data[$field['id']])){$dob=$data[$field['id']];break;}}}
   $missing=[];foreach(['name'=>$member->name,'email'=>$member->email,'phone'=>$member->phone,'dob'=>$dob] as $key=>$value)if(blank($value))$missing[]=$key;
   DB::table('church_members')->where('id',$member->id)->update(['missing_fields'=>json_encode($missing)]);
  }});
  Schema::create('member_import_drafts',function(Blueprint $t){$t->id();$t->foreignId('church_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->text('rows');$t->unsignedInteger('revision')->default(1);$t->timestamps();$t->index(['church_id','user_id']);});
 }
 public function down(): void {Schema::dropIfExists('member_import_drafts');Schema::table('church_members',function(Blueprint $t){$t->dropColumn(['missing_fields','missing_data_authorization']);});}
};
