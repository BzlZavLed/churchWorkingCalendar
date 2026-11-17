<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestampTz('opening_prayer_recorded_at')->nullable();
            $table->timestampTz('opening_remarks_recorded_at')->nullable();
            $table->text('closing_notes')->nullable();
        });
    }
    public function down(): void {
        Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn(['opening_prayer_recorded_at', 'opening_remarks_recorded_at', 'closing_notes']));
    }
};
