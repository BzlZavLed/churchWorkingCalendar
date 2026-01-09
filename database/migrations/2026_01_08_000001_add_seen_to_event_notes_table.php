<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_notes', function (Blueprint $table) {
            $table->boolean('seen_note')->default(false)->after('replied_at');
            $table->timestamp('seen_at')->nullable()->after('seen_note');
            $table->foreignId('seen_by_user_id')->nullable()->after('seen_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seen_by_user_id');
            $table->dropColumn(['seen_note', 'seen_at']);
        });
    }
};
