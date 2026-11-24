<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('visitor_forms',fn(Blueprint $t)=>$t->json('layout')->nullable());
  Schema::create('visitor_form_images',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignId('church_id')->constrained()->cascadeOnDelete();$t->string('path');$t->timestamps();});
 }
 public function down():void {Schema::dropIfExists('visitor_form_images');Schema::table('visitor_forms',fn(Blueprint $t)=>$t->dropColumn('layout'));}
};
