<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('church_roles',function(Blueprint $t){$t->id();$t->foreignId('church_id')->constrained()->cascadeOnDelete();$t->string('key');$t->string('name',100);$t->string('base_role');$t->json('permissions');$t->unsignedInteger('revision')->default(1);$t->boolean('is_builtin')->default(false);$t->timestamps();$t->unique(['church_id','key']);$t->unique(['church_id','name']);});
  Schema::table('users',fn(Blueprint $t)=>$t->foreignId('church_role_id')->nullable()->constrained()->restrictOnDelete());
 }
 public function down(): void {Schema::table('users',fn(Blueprint $t)=>$t->dropConstrainedForeignId('church_role_id'));Schema::dropIfExists('church_roles');}
};
