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
        if (!Schema::hasColumn('users', 'church_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('church_id')->nullable()->after('email')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'church_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['church_id']);
                $table->dropColumn('church_id');
            });
        }
    }
};
