<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::create('committee_reports',function(Blueprint $table){
  $table->id();$table->foreignId('committee_id')->constrained()->cascadeOnDelete();
  $table->string('title');$table->string('original_name');$table->string('path');$table->string('mime_type');$table->unsignedBigInteger('size');
  $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();
 });}
 public function down(): void {Schema::dropIfExists('committee_reports');}
};
