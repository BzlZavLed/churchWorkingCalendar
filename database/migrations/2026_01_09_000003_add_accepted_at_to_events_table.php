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
        if (!Schema::hasTable('events')) {
            return;
        }

        if (!Schema::hasColumn('events', 'accepted_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->timestamp('accepted_at')->nullable()->after('final_validation');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        if (Schema::hasColumn('events', 'accepted_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('accepted_at');
            });
        }
    }
};
