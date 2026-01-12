<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('departments')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'is_club')) {
                $table->boolean('is_club')->default(false)->after('user_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('departments')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'is_club')) {
                $table->dropColumn('is_club');
            }
        });
    }
};
