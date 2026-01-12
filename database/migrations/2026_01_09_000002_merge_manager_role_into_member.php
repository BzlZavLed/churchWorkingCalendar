<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('users')) {
            return;
        }

        DB::table('users')
            ->where('role', 'manager')
            ->update(['role' => 'member']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('users')) {
            return;
        }

        DB::table('users')
            ->where('role', 'member')
            ->update(['role' => 'manager']);
    }
};
