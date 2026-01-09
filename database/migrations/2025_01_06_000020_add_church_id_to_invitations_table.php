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
        if (!Schema::hasColumn('invitations', 'church_id')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->foreignId('church_id')->after('code')->constrained()->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('invitations', 'church_id')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->dropForeign(['church_id']);
                $table->dropColumn('church_id');
            });
        }
    }
};
