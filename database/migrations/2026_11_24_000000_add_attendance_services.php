<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('attendance_services',function(Blueprint $t){
   $t->id();$t->foreignId('church_id')->constrained()->cascadeOnDelete();$t->string('name',150);
   $t->unsignedTinyInteger('weekday');$t->string('start_time',5);$t->string('end_time',5);$t->boolean('active')->default(true);$t->timestamps();
  });
  Schema::create('attendance_sessions',function(Blueprint $t){
   $t->id();$t->foreignId('church_id')->constrained()->cascadeOnDelete();$t->foreignId('attendance_service_id')->nullable()->constrained()->nullOnDelete();
   $t->uuid('batch_id');$t->string('name',150);$t->date('service_date');$t->string('timezone');$t->timestamp('starts_at');$t->timestamp('ends_at');
   $t->timestamp('opened_at');$t->timestamp('closed_at')->nullable();$t->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();
   $t->unique(['attendance_service_id','service_date']);$t->index(['church_id','service_date']);
  });
  Schema::table('member_attendances',function(Blueprint $t){
   $t->dropUnique(['church_member_id','attendance_date']);
   $t->foreignId('attendance_session_id')->nullable()->constrained()->restrictOnDelete();
   $t->unique(['church_member_id','attendance_session_id'],'member_service_attendance_unique');
  });
 }
 public function down(): void {
  if(\Illuminate\Support\Facades\DB::table('member_attendances')->select('church_member_id','attendance_date')->groupBy('church_member_id','attendance_date')->havingRaw('COUNT(*) > 1')->exists())throw new RuntimeException('Cannot restore daily uniqueness while multiple service records exist.');
  Schema::table('member_attendances',function(Blueprint $t){$t->dropUnique('member_service_attendance_unique');$t->dropConstrainedForeignId('attendance_session_id');$t->unique(['church_member_id','attendance_date']);});
  Schema::dropIfExists('attendance_sessions');Schema::dropIfExists('attendance_services');
 }
};
