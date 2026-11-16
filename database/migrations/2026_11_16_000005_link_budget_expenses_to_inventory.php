<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('budget_entries', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_hash', 64)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('budget_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn('request_hash');
        });
    }
};
