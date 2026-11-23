<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,Department,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberAttendanceTest extends TestCase
{
 use RefreshDatabase;
 public function test_family_lookup_is_scoped_private_and_attendance_is_idempotent(): void {
  $this->travelTo(now()->setDate(2026,11,18)->setTime(16,0));
  $church=Church::create(['name'=>'Attendance']);
  $other=Church::create(['name'=>'Other']);
  $clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);
  $make=fn($church,$name,$surname)=>ChurchMember::create(['member_template_id'=>\App\Models\MemberTemplate::firstOrCreate(['church_id'=>$church->id,'version'=>1],['fields'=>[]])->id,'church_id'=>$church->id,'name'=>$name,'first_surname'=>$surname,'email'=>uniqid().'@example.test','phone'=>'5551234567','data'=>['private'=>'secret']]);
  $one=$make($church,'Ana García López','García');$two=$make($church,'Luis García Pérez','garcía');
  $foreign=$make($other,'Private García','García');$make($church,'Other Garcia','Garcia');
  $this->getJson('/api/attendance/surnames?q=Ga')->assertUnauthorized();
  Sanctum::actingAs($clerk);
  $this->getJson('/api/attendance/surnames?q=Ga')->assertOk()->assertJsonCount(2,'surnames');
  $this->getJson('/api/attendance/surnames?q=%25%25')->assertOk()->assertJsonCount(0,'surnames');
  $this->getJson('/api/attendance/family?surname=Garc%C3%ADa&timezone=America/New_York')->assertOk()->assertJsonCount(2,'members')->assertJsonMissing(['email'=>$one->email])->assertJsonMissing(['private'=>'secret']);
  $payload=['member_id'=>$one->id,'date'=>'2026-11-18','timezone'=>'America/New_York','present'=>true];
  $this->postJson('/api/attendance',$payload)->assertOk();$this->postJson('/api/attendance',$payload)->assertOk();
  $this->assertDatabaseCount('member_attendances',1);
  $this->postJson('/api/attendance',array_replace($payload,['member_id'=>$two->id]))->assertOk();
  $this->assertDatabaseCount('member_attendances',2);
  $this->postJson('/api/attendance',array_replace($payload,['member_id'=>$foreign->id]))->assertNotFound();
  $this->postJson('/api/attendance',array_replace($payload,['present'=>false]))->assertOk();$this->assertDatabaseCount('member_attendances',1);
  $this->postJson('/api/attendance',array_replace($payload,['date'=>'2026-11-17']))->assertConflict();
  $greeter=Department::create(['church_id'=>$church->id,'name'=>'Welcome','is_greeting'=>true]);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member','department_id'=>$greeter->id]));
  $this->getJson('/api/attendance/surnames?q=Ga')->assertOk();
  $this->postJson('/api/attendance',$payload)->assertOk();
  $this->getJson('/api/members')->assertForbidden();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));
  $this->getJson('/api/attendance/surnames?q=Ga')->assertForbidden();$this->postJson('/api/attendance',$payload)->assertForbidden();
 }
 public function test_public_qr_checkin_uses_family_ids_and_scoped_expiring_tokens(): void {
  $church=Church::create(['name'=>'QR']);
  $other=Church::create(['name'=>'Other']);
  $template=app(\App\Services\MemberRecords::class)->current($church->id);
  $make=fn($name,$surname,$family)=>ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template->id,'name'=>$name,'first_surname'=>$surname,'email'=>'private@example.test','phone'=>'5551234567','data'=>['FamilyId'=>$family]]);
  $one=$make('Ana Test','Test','f1');$two=$make('Luis Different','Different','f1');$three=$make('Unrelated Test','Test','f2');$four=$make('Not matched','Other','f3');
  $url='/api/public/churches/'.$church->id.'/attendance';
  $roster=$this->getJson($url.'/family?surname=Test&timezone=America/New_York')->assertOk()->assertJsonCount(3,'members')->assertJsonMissing(['email'=>'private@example.test'])->json();
  $payload=['member_ids'=>[$one->id,$two->id],'token'=>$roster['token']];
  $this->postJson($url,$payload)->assertOk();$this->postJson($url,$payload)->assertOk();
  $this->assertDatabaseCount('member_attendances',2);
  $this->assertDatabaseHas('member_attendances',['church_member_id'=>$one->id,'recorded_by'=>null]);
  $this->postJson($url,['member_ids'=>[$four->id],'token'=>$roster['token']])->assertUnprocessable();
  $this->postJson('/api/public/churches/'.$other->id.'/attendance',$payload)->assertUnprocessable();
  $this->postJson($url,['member_ids'=>[$three->id],'token'=>'bad'])->assertUnprocessable();
  $this->travel(16)->minutes();
  $this->postJson($url,$payload)->assertUnprocessable();
 }
 public function test_split_names_preserve_full_display_name(): void {
  $church=Church::create(['name'=>'Names']);Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $template=$this->getJson('/api/members')->assertOk()->json('template.id');
  $payload=['template_id'=>$template,'given_name'=>'Ana María','first_surname'=>'De la Cruz','second_surname'=>'López','email'=>'ana@example.test','phone'=>'5551234567','data'=>['BornOn'=>'1990-01-01']];
  $this->postJson('/api/members',$payload)->assertOk()->assertJsonPath('name','Ana María De la Cruz López')->assertJsonPath('first_surname','De la Cruz');
  $this->postJson('/api/members',array_replace($payload,['first_surname'=>'','email'=>'other@example.test']))->assertOk();
 }
}
