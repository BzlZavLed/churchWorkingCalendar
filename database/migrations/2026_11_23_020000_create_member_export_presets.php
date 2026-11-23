<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('member_export_presets',function(Blueprint $table){
   $table->id();$table->foreignId('church_id')->constrained()->cascadeOnDelete();
   $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->string('name',100);$table->json('columns');$table->timestamps();
   $table->unique(['church_id','user_id','name']);
  });
 }
 public function down(): void {Schema::dropIfExists('member_export_presets');}
};
