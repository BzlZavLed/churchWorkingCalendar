<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('church_contacts', 'sms_consent')) {
                $table->boolean('sms_consent')->default(false)->after('is_sda');
            }
            if (!Schema::hasColumn('church_contacts', 'sms_consented_at')) {
                $table->timestamp('sms_consented_at')->nullable()->after('sms_consent');
            }
            if (!Schema::hasColumn('church_contacts', 'email_consent')) {
                $table->boolean('email_consent')->default(false)->after('sms_consented_at');
            }
            if (!Schema::hasColumn('church_contacts', 'email_consented_at')) {
                $table->timestamp('email_consented_at')->nullable()->after('email_consent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('church_contacts', function (Blueprint $table) {
            foreach (['email_consented_at', 'email_consent', 'sms_consented_at', 'sms_consent'] as $column) {
                if (Schema::hasColumn('church_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
