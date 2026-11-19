<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('church_members', function(Blueprint $table) {
   $table->string('given_name')->nullable();
   $table->string('first_surname')->nullable();
   $table->string('second_surname')->nullable();
   $table->index(['church_id','first_surname']);
  });
  Schema::create('member_attendances', function(Blueprint $table) {
   $table->id();
   $table->foreignId('church_id')->constrained()->cascadeOnDelete();
   $table->foreignId('church_member_id')->constrained()->cascadeOnDelete();
   $table->date('attendance_date');
   $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamps();
   $table->unique(['church_member_id','attendance_date']);
   $table->index(['church_id','attendance_date']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('member_attendances');
  Schema::table('church_members', function(Blueprint $table) {
   $table->dropIndex(['church_id','first_surname']);
   $table->dropColumn(['given_name','first_surname','second_surname']);
  });
 }
};
