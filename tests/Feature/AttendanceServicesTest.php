<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,AttendanceSession,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceServicesTest extends TestCase {
 use RefreshDatabase;
 public function test_manual_opening_cutoff_closure_and_reopening(): void {
  $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-28 10:00','America/New_York'));
  $church=Church::create(['name'=>'Services']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $base=['weekday'=>6,'active'=>true];
  $one=$this->postJson('/api/attendance/services',$base+['name'=>'School','start_time'=>'10:30','end_time'=>'12:00'])->assertCreated()->json('id');
  $two=$this->postJson('/api/attendance/services',$base+['name'=>'Worship','start_time'=>'12:00','end_time'=>'14:00'])->assertCreated()->json('id');
  $url='/api/public/churches/'.$church->id.'/attendance';
  $this->getJson($url.'/status')->assertOk()->assertJsonCount(0,'services');
  $open=['service_ids'=>[$one,$two],'timezone'=>'America/New_York','date'=>'2026-11-28'];
  $this->postJson('/api/attendance/sessions',array_replace($open,['date'=>'2026-12-05']))->assertUnprocessable();
  $sessions=$this->postJson('/api/attendance/sessions',$open)->assertOk()->json('sessions');
  $this->getJson($url.'/status')->assertOk()->assertJsonCount(2,'services');
  $template=app(\App\Services\MemberRecords::class)->current($church->id);
  $member=ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template->id,'name'=>'Ana Test','first_surname'=>'Test','email'=>'test@example.test','phone'=>'5551234567','data'=>[]]);
  $roster=$this->getJson($url.'/family?surname=Test&timezone=America/New_York')->assertOk()->json();
  $payload=['member_ids'=>[$member->id],'token'=>$roster['token']];
  $this->postJson($url,$payload)->assertOk();
  $this->postJson($url,$payload)->assertOk();
  $this->assertDatabaseCount('member_attendances',2);
  $late=ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template->id,'name'=>'Late Test','first_surname'=>'Late','email'=>'late@example.test','phone'=>'5551234567','data'=>[]]);
  $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-28 11:59','America/New_York'));
  $token=$this->getJson($url.'/family?surname=Late&timezone=America/New_York')->assertOk()->json('token');
  $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-28 12:00','America/New_York'));
  $this->postJson($url,['member_ids'=>[$late->id],'token'=>$token])->assertOk()->assertJsonCount(1,'services');
  $this->assertDatabaseCount('member_attendances',3);
  $this->assertDatabaseHas('member_attendances',['church_member_id'=>$late->id,'attendance_session_id'=>$sessions[1]['id']]);
  $this->patchJson('/api/attendance/sessions/'.$sessions[0]['id'],['open'=>false])->assertOk();
  $this->postJson($url,['member_ids'=>[$late->id],'token'=>$token])->assertConflict();
  $this->patchJson('/api/attendance/sessions/'.$sessions[0]['id'],['open'=>true])->assertOk();
  $this->getJson($url.'/status')->assertOk()->assertJsonCount(1,'services');
  $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-28 14:00','America/New_York'));
  $this->getJson($url.'/status')->assertOk()->assertJsonCount(0,'services');
  $this->patchJson('/api/attendance/sessions/'.$sessions[0]['id'],['open'=>true])->assertUnprocessable();
 }
 public function test_service_management_is_scoped_and_requires_management_capability(): void {
  $church=Church::create(['name'=>'One']);$other=Church::create(['name'=>'Two']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $data=['name'=>'Friday','weekday'=>5,'start_time'=>'19:30','end_time'=>'21:00','active'=>true];
  $id=$this->postJson('/api/attendance/services',$data)->assertCreated()->json('id');
  Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']));
  $this->putJson('/api/attendance/services/'.$id,$data)->assertNotFound();
  $this->getJson('/api/attendance/services')->assertOk()->assertJsonCount(0,'services');
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));
  $this->postJson('/api/attendance/services',$data)->assertForbidden();
 }
}
