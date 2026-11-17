<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('event_notes',fn(Blueprint $table)=>$table->boolean('author_is_reviewer')->default(false));
  DB::table('event_notes')->whereIn('author_role',['secretary','superadmin'])->update(['author_is_reviewer'=>true]);
 }
 public function down(): void {Schema::table('event_notes',fn(Blueprint $table)=>$table->dropColumn('author_is_reviewer'));}
};
