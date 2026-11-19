<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('churches', function(Blueprint $table) {
  $table->boolean('clerk_whatsapp_enabled')->default(true);
  $table->boolean('clerk_ai_enabled')->default(true);
 }); }
 public function down(): void { Schema::table('churches',fn(Blueprint $table)=>$table->dropColumn(['clerk_whatsapp_enabled','clerk_ai_enabled'])); }
};
