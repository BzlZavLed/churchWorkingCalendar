<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('churches', fn (Blueprint $table) => $table->boolean('budget_self_management')->default(false));
        Schema::create('department_budget_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->bigInteger('expected_cents');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_budget_plans');
        Schema::table('churches', fn (Blueprint $table) => $table->dropColumn('budget_self_management'));
    }
};
