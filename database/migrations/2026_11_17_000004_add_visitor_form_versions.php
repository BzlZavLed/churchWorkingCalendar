<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('visitor_forms', function(Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('fields');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['church_id','version']);
        });
        Schema::table('church_contacts', function(Blueprint $table) {
            $table->foreignId('visitor_form_id')->nullable()->constrained('visitor_forms')->restrictOnDelete();
            $table->json('custom_answers')->nullable();
        });
    }
    public function down(): void {
        Schema::table('church_contacts', function(Blueprint $table) {
            $table->dropConstrainedForeignId('visitor_form_id');
            $table->dropColumn('custom_answers');
        });
        Schema::dropIfExists('visitor_forms');
    }
};
