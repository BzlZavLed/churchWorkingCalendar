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
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('church_id');
                $table->string('name');
                $table->string('color')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('churches') && Schema::hasColumn('departments', 'church_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropForeign(['church_id']);
            });
        }

        Schema::dropIfExists('departments');
    }
};
