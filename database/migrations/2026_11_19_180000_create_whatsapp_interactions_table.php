<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            $table->text('content');
            $table->string('status', 30);
            $table->foreignId('message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'church_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('whatsapp_interactions'); }
};
