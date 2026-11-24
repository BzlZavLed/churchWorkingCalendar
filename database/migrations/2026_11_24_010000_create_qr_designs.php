<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('qr_designs',function(Blueprint $t){$t->id();$t->foreignId('church_id')->constrained()->cascadeOnDelete();$t->string('kind');$t->json('design');$t->timestamps();$t->unique(['church_id','kind']);});}
 public function down():void {Schema::dropIfExists('qr_designs');}
};
