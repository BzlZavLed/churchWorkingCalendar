<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,MemberTemplate,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class AttendanceReportTest extends TestCase {
 use RefreshDatabase;
 public function test_date_and_member_filters_and_church_scope(): void {
  $church=Church::create(['name'=>'Reports']);$other=Church::create(['name'=>'Other']);
  $make=function($church,$name){$template=MemberTemplate::firstOrCreate(['church_id'=>$church->id,'version'=>1],['fields'=>[]]);return ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template->id,'name'=>$name,'email'=>$name.'@example.test','phone'=>'5551234567','data'=>[]]);};
  $ana=$make($church,'Ana');$luis=$make($church,'Luis');$foreign=$make($other,'Private');
  foreach([[$ana,'2026-11-01'],[$ana,'2026-11-02'],[$luis,'2026-11-02'],[$foreign,'2026-11-02'],[$ana,'2026-10-31']] as [$member,$date]) DB::table('member_attendances')->insert(['church_id'=>$member->church_id,'church_member_id'=>$member->id,'attendance_date'=>$date,'created_at'=>now(),'updated_at'=>now()]);
  $url='/api/reports/attendance?from=2026-11-01&to=2026-11-03';
  $this->getJson($url)->assertUnauthorized();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $this->getJson($url)->assertOk()->assertJsonPath('summary.check_ins',3)->assertJsonPath('summary.members',2)->assertJsonPath('summary.recorded_days',2)->assertJsonPath('series.2.count',0)->assertJsonCount(2,'members')->assertJsonMissing(['name'=>'Private'])->assertJsonMissing(['email'=>$ana->email]);
  $this->getJson($url.'&member_id='.$ana->id)->assertOk()->assertJsonCount(2,'records')->assertJsonPath('summary.members',1);
  $this->getJson($url.'&member_id='.$foreign->id)->assertNotFound();
  $this->getJson('/api/reports/attendance?from=2026-11-03&to=2026-11-01')->assertUnprocessable();
  $this->getJson('/api/reports/attendance?from=2025-01-01&to=2026-11-01')->assertUnprocessable();
  $this->getJson('/api/reports/attendance?from=2026-12-01&to=2026-12-02')->assertOk()->assertJsonPath('summary.check_ins',0)->assertJsonCount(2,'series');
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'admin']));
  $this->getJson($url)->assertForbidden();
 }
}
