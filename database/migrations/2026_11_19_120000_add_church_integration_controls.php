<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('churches',function(Blueprint $t){
   $t->boolean('whatsapp_enabled')->default(true);$t->boolean('ai_enabled')->default(true);
   $t->unsignedInteger('whatsapp_ai_limit')->nullable();$t->unsignedInteger('whatsapp_replies_limit')->nullable();$t->unsignedInteger('whatsapp_inbox_limit')->nullable();$t->unsignedInteger('whatsapp_daily_limit')->nullable();$t->unsignedInteger('birthday_ai_limit')->nullable();
  });
  Schema::table('whatsapp_accounts',function(Blueprint $t){$t->string('identity_hash',64)->nullable()->unique();$t->text('registered_sender')->nullable();});
  DB::table('whatsapp_accounts')->whereNotNull('sender_hash')->update(['identity_hash'=>DB::raw('sender_hash')]);
  if($church=config('whatsapp.church_id')) {
   foreach(DB::table('whatsapp_usage')->where('bucket','like','global:%')->get() as $row) DB::table('whatsapp_usage')->insertOrIgnore(['bucket'=>str_replace('global:',"church:$church:",$row->bucket),'used'=>$row->used]);
  }
  Schema::create('integration_usage',function(Blueprint $t){$t->id();$t->foreignId('church_id')->constrained()->cascadeOnDelete();$t->string('month',7);$t->string('feature',30);$t->unsignedBigInteger('input_tokens')->default(0);$t->unsignedBigInteger('output_tokens')->default(0);$t->unsignedBigInteger('responses')->default(0);$t->unique(['church_id','month','feature']);});
 }
 public function down(): void {
  Schema::dropIfExists('integration_usage');
  Schema::table('whatsapp_accounts',function(Blueprint $t){$t->dropUnique(['identity_hash']);$t->dropColumn(['identity_hash','registered_sender']);});
  Schema::table('churches',fn(Blueprint $t)=>$t->dropColumn(['whatsapp_enabled','ai_enabled','whatsapp_ai_limit','whatsapp_replies_limit','whatsapp_inbox_limit','whatsapp_daily_limit','birthday_ai_limit']));
 }
};
