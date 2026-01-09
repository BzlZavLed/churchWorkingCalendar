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
        if (!Schema::hasTable('invitations')) {
            Schema::create('invitations', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->unsignedBigInteger('church_id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('role')->default('member');
                $table->string('email')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('max_uses')->default(1);
                $table->unsignedInteger('uses_count')->default(0);
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index(['code', 'revoked_at']);
            });
        }

        if (Schema::hasTable('churches') && Schema::hasColumn('invitations', 'church_id')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('departments') && Schema::hasColumn('invitations', 'department_id')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('invitations', 'created_by')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invitations')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->dropForeign(['church_id']);
                $table->dropForeign(['department_id']);
                $table->dropForeign(['created_by']);
            });
        }

        Schema::dropIfExists('invitations');
    }
};
