<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('church_contacts', 'consent_token')) {
                $table->string('consent_token', 64)->nullable()->after('email_consented_at');
            }
        });

        DB::table('church_contacts')
            ->whereNull('consent_token')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($contact) {
                DB::table('church_contacts')
                    ->where('id', $contact->id)
                    ->update(['consent_token' => Str::random(64)]);
            });

        Schema::table('church_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('church_contacts', 'consent_token')) {
                $table->unique('consent_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('church_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('church_contacts', 'consent_token')) {
                $table->dropUnique(['consent_token']);
                $table->dropColumn('consent_token');
            }
        });
    }
};
