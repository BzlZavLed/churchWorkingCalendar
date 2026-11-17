<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('church_contacts', fn(Blueprint $t) => $t->json('original_custom_answers')->nullable()); }
 public function down(): void { Schema::table('church_contacts', fn(Blueprint $t) => $t->dropColumn('original_custom_answers')); }
};
