<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  Schema::table('meetings', function(Blueprint $table) { $table->unsignedInteger('minutes_number')->nullable(); $table->unique(['church_id','minutes_number']); });
  DB::transaction(function () {
   foreach (DB::table('meetings')->whereNotNull('end_at')->select('church_id')->distinct()->pluck('church_id') as $churchId) {
    $number=0;
    foreach(DB::table('meetings')->where('church_id',$churchId)->whereNotNull('end_at')->orderBy('end_at')->orderBy('id')->pluck('id') as $id) DB::table('meetings')->where('id',$id)->update(['minutes_number'=>++$number]);
   }
  });
 }
 public function down(): void { Schema::table('meetings',function(Blueprint $table){$table->dropUnique(['church_id','minutes_number']);$table->dropColumn('minutes_number');}); }
};
