<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('member_export_presets',function(Blueprint $t){$t->string('sort_by')->nullable();$t->string('sort_direction',4)->default('asc');});}
 public function down(): void {Schema::table('member_export_presets',fn(Blueprint $t)=>$t->dropColumn(['sort_by','sort_direction']));}
};
