<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('meetings', function (Blueprint $table) { $table->json('attendance')->nullable(); });
        Schema::table('meeting_points', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
            $table->json('history')->nullable();
            $table->json('revision')->nullable();
        });
    }
    public function down(): void {
        Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn('attendance'));
        Schema::table('meeting_points', fn (Blueprint $table) => $table->dropColumn(['history', 'revision']));
        // Preserve nullable departments: clerk-authored points have no department.
    }
};
