<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('member_templates', function(Blueprint $t) {
            $t->id(); $t->foreignId('church_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version'); $t->json('fields'); $t->timestamps();
            $t->unique(['church_id','version']);
        });
        Schema::create('church_members', function(Blueprint $t) {
            $t->id(); $t->foreignId('church_id')->constrained()->cascadeOnDelete();
            $t->foreignId('member_template_id')->constrained()->restrictOnDelete();
            $t->string('name'); $t->string('email'); $t->string('phone',50); $t->json('data');
            $t->timestamps(); $t->unique(['church_id','email']);
        });
    }
    public function down(): void { Schema::dropIfExists('church_members'); Schema::dropIfExists('member_templates'); }
};
