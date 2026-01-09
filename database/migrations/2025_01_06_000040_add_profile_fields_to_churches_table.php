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
        if (!Schema::hasTable('churches')) {
            return;
        }

        $hasName = Schema::hasColumn('churches', 'name');

        Schema::table('churches', function (Blueprint $table) use ($hasName) {
            if (!Schema::hasColumn('churches', 'conference_name')) {
                $column = $table->string('conference_name')->nullable();
                if ($hasName) {
                    $column->after('name');
                }
            }

            if (!Schema::hasColumn('churches', 'pastor_name')) {
                $column = $table->string('pastor_name')->nullable();
                if ($hasName) {
                    $column->after('conference_name');
                }
            }

            if (!Schema::hasColumn('churches', 'address')) {
                $column = $table->string('address')->nullable();
                if ($hasName) {
                    $column->after('pastor_name');
                }
            }

            if (!Schema::hasColumn('churches', 'ethnicity')) {
                $column = $table->string('ethnicity')->nullable();
                if ($hasName) {
                    $column->after('address');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('churches')) {
            return;
        }

        $columns = [];
        foreach (['conference_name', 'pastor_name', 'address', 'ethnicity'] as $column) {
            if (Schema::hasColumn('churches', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns) {
            Schema::table('churches', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
