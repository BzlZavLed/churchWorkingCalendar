<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('generation')->default(0);
            $t->string('sender_hash', 64)->nullable()->unique();
            $t->text('sender')->nullable();
            $t->string('link_hash', 64)->nullable()->unique();
            $t->timestamp('link_expires_at')->nullable();
            $t->timestamp('last_received_at')->nullable();
            $t->text('state')->nullable();
            $t->timestamps();
        });
        Schema::create('whatsapp_messages', function (Blueprint $t) {
            $t->id();
            $t->string('provider_id')->unique();
            $t->foreignId('account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
            $t->unsignedInteger('generation')->default(0);
            $t->text('body')->nullable();
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('whatsapp_usage', function (Blueprint $t) {
            $t->string('bucket')->primary();
            $t->unsignedInteger('used')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_accounts');
        Schema::dropIfExists('whatsapp_usage');
    }
};
