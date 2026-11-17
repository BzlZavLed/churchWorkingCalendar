<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Eloquent writes UTC wall-clock values, as with the other meeting timestamps.
        // PostgreSQL casts using the connection timezone, preserving those stored values.
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('opening_prayer_recorded_at')->nullable()->change();
            $table->timestamp('opening_remarks_recorded_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestampTz('opening_prayer_recorded_at')->nullable()->change();
            $table->timestampTz('opening_remarks_recorded_at')->nullable()->change();
        });
    }
};
