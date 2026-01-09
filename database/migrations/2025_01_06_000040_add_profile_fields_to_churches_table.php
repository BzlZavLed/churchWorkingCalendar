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
        Schema::table('churches', function (Blueprint $table) {
            $table->string('conference_name')->nullable()->after('name');
            $table->string('pastor_name')->nullable()->after('conference_name');
            $table->string('address')->nullable()->after('pastor_name');
            $table->string('ethnicity')->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn(['conference_name', 'pastor_name', 'address', 'ethnicity']);
        });
    }
};
