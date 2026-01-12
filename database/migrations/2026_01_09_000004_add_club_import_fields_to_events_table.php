<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'external_source')) {
                $table->string('external_source')->nullable()->after('published_at');
            }
            if (!Schema::hasColumn('events', 'external_id')) {
                $table->string('external_id')->nullable()->after('external_source');
            }
            if (!Schema::hasColumn('events', 'is_club_event')) {
                $table->boolean('is_club_event')->default(false)->after('external_id');
            }
            if (!Schema::hasColumn('events', 'club_type')) {
                $table->string('club_type')->nullable()->after('is_club_event');
            }
            if (!Schema::hasColumn('events', 'plan_name')) {
                $table->string('plan_name')->nullable()->after('club_type');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'external_source') && Schema::hasColumn('events', 'external_id')) {
                $table->unique(['external_source', 'external_id'], 'events_external_source_external_id_unique');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'external_source') && Schema::hasColumn('events', 'external_id')) {
                $table->dropUnique('events_external_source_external_id_unique');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'plan_name')) {
                $table->dropColumn('plan_name');
            }
            if (Schema::hasColumn('events', 'club_type')) {
                $table->dropColumn('club_type');
            }
            if (Schema::hasColumn('events', 'is_club_event')) {
                $table->dropColumn('is_club_event');
            }
            if (Schema::hasColumn('events', 'external_id')) {
                $table->dropColumn('external_id');
            }
            if (Schema::hasColumn('events', 'external_source')) {
                $table->dropColumn('external_source');
            }
        });
    }
};
