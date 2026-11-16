<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', fn (Blueprint $table) => $table->boolean('is_treasury')->default(false));
        Schema::create('department_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('period', 40);
            $table->bigInteger('opening_cents')->default(0);
            $table->string('opening_source');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'period']);
        });
        Schema::create('budget_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('period', 40);
            $table->uuid('request_id')->unique();
            $table->string('payload_hash', 64);
            $table->string('kind');
            $table->string('description');
            $table->date('occurred_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('budget_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_publication_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('request_id')->nullable()->unique();
            $table->string('kind');
            $table->bigInteger('amount_cents');
            $table->string('description');
            $table->date('occurred_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_entries');
        Schema::dropIfExists('budget_publications');
        Schema::dropIfExists('department_budgets');
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn('is_treasury'));
    }
};
