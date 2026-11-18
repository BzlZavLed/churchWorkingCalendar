<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('committees',fn(Blueprint $table)=>$table->foreignId('formation_meeting_id')->nullable()->constrained('meetings')->restrictOnDelete());}
 public function down(): void {Schema::table('committees',fn(Blueprint $table)=>$table->dropConstrainedForeignId('formation_meeting_id'));}
};
