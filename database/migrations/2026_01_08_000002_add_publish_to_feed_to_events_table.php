<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('publish_to_feed')->default(false)->after('final_validation');
            $table->timestamp('published_at')->nullable()->after('publish_to_feed');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['publish_to_feed', 'published_at']);
        });
    }
};
