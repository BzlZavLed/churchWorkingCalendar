<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
            $table->foreignId('church_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_conference_event')->default(false);
        });
        Schema::table('meeting_points', function (Blueprint $table) {
            $table->boolean('calendar_review')->default(false);
            $table->date('calendar_from')->nullable();
            $table->date('calendar_until')->nullable();
            $table->json('calendar_decisions')->nullable();
        });
    }
    public function down(): void {
        Schema::table('meeting_points', fn(Blueprint $table) => $table->dropColumn(['calendar_review','calendar_from','calendar_until','calendar_decisions']));
        Schema::table('events', function(Blueprint $table) {
            $table->dropConstrainedForeignId('church_id');
            $table->dropColumn('is_conference_event');
        });
    }
};
