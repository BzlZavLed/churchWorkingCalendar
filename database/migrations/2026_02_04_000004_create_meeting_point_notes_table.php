<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('meeting_point_notes')) {
            return;
        }

        Schema::create('meeting_point_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_point_id')->constrained('meeting_points')->cascadeOnDelete();
            $table->text('note');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['meeting_point_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_point_notes');
    }
};
