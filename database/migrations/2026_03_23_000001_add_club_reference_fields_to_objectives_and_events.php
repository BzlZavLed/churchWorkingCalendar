<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('objectives')) {
            Schema::table('objectives', function (Blueprint $table) {
                if (!Schema::hasColumn('objectives', 'external_source')) {
                    $table->string('external_source')->nullable()->after('evaluation_metrics');
                }
                if (!Schema::hasColumn('objectives', 'external_id')) {
                    $table->string('external_id')->nullable()->after('external_source');
                }
            });

            Schema::table('objectives', function (Blueprint $table) {
                if (
                    Schema::hasColumn('objectives', 'department_id')
                    && Schema::hasColumn('objectives', 'external_source')
                    && Schema::hasColumn('objectives', 'external_id')
                ) {
                    $table->unique(
                        ['department_id', 'external_source', 'external_id'],
                        'objectives_department_external_source_external_id_unique'
                    );
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'objective_name_snapshot')) {
                    $table->string('objective_name_snapshot')->nullable()->after('objective_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('objectives')) {
            Schema::table('objectives', function (Blueprint $table) {
                if (
                    Schema::hasColumn('objectives', 'department_id')
                    && Schema::hasColumn('objectives', 'external_source')
                    && Schema::hasColumn('objectives', 'external_id')
                ) {
                    $table->dropUnique('objectives_department_external_source_external_id_unique');
                }
            });

            Schema::table('objectives', function (Blueprint $table) {
                if (Schema::hasColumn('objectives', 'external_id')) {
                    $table->dropColumn('external_id');
                }
                if (Schema::hasColumn('objectives', 'external_source')) {
                    $table->dropColumn('external_source');
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'objective_name_snapshot')) {
                    $table->dropColumn('objective_name_snapshot');
                }
            });
        }
    }
};
