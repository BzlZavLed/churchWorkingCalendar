<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        if (!Schema::hasColumn('churches', 'slug')) {
            Schema::table('churches', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique();
            });
        }

        $churches = DB::table('churches')->select('id', 'name', 'slug')->orderBy('id')->get();
        if ($churches->isEmpty()) {
            return;
        }

        $used = [];
        foreach ($churches as $church) {
            if (!empty($church->slug)) {
                $used[$church->slug] = true;
                continue;
            }

            $base = Str::slug($church->name ?? '') ?: 'church';
            $slug = $base;
            $suffix = 2;
            while (isset($used[$slug])) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            DB::table('churches')
                ->where('id', $church->id)
                ->update(['slug' => $slug]);

            $used[$slug] = true;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('churches')) {
            return;
        }

        if (Schema::hasColumn('churches', 'slug')) {
            Schema::table('churches', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            });
        }
    }
};
