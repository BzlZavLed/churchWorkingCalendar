<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('email_activity',function(Blueprint $table){$table->id();$table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();$table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();$table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();$table->string('recipient_email');$table->string('kind');$table->string('status');$table->timestamp('created_at')->useCurrent();$table->index(['created_at','id']);});}
 public function down():void {Schema::dropIfExists('email_activity');}
};
