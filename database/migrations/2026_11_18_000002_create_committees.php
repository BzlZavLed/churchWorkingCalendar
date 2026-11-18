<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('committees',function(Blueprint $table){
   $table->id();$table->foreignId('church_id')->constrained()->cascadeOnDelete();
   $table->string('name');$table->text('objective');$table->date('formation_voted_on');$table->string('minutes_number',100);
   $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
   $table->unsignedInteger('revision')->default(1);$table->timestamps();
  });
  Schema::create('committee_members',function(Blueprint $table){
   $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
   $table->foreignId('church_member_id')->constrained()->restrictOnDelete();
   $table->primary(['committee_id','church_member_id']);
  });
 }
 public function down(): void {Schema::dropIfExists('committee_members');Schema::dropIfExists('committees');}
};
