<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::table('church_members',function(Blueprint $t){
   $t->dropUnique(['church_id','email']);
   $t->string('source_person_id',500)->nullable();
   $t->unique(['church_id','source_person_id']);
  });
 }
 public function down(): void {
  if(DB::table('church_members')->select('church_id','email')->groupBy('church_id','email')->havingRaw('COUNT(*) > 1')->exists()) throw new RuntimeException('Shared member emails exist. Resolve duplicates before rolling back.');
  Schema::table('church_members',function(Blueprint $t){$t->dropUnique(['church_id','source_person_id']);$t->dropColumn('source_person_id');$t->unique(['church_id','email']);});
 }
};
