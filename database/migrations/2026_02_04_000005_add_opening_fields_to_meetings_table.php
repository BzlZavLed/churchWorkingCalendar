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
        if (!Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'opened_by')) {
                $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('meetings', 'opening_prayer')) {
                $table->text('opening_prayer')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'opening_remarks')) {
                $table->text('opening_remarks')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'opened_by')) {
                $table->dropConstrainedForeignId('opened_by');
            }
            if (Schema::hasColumn('meetings', 'opening_prayer')) {
                $table->dropColumn('opening_prayer');
            }
            if (Schema::hasColumn('meetings', 'opening_remarks')) {
                $table->dropColumn('opening_remarks');
            }
        });
    }
};
