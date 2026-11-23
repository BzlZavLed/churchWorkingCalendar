<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class MemberExportTest extends TestCase {
 use RefreshDatabase;
 public function test_sorting_dates_postcodes_and_missing_values_and_saved_order(): void {
  $church=Church::create(['name'=>'Sorting']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $template=$this->getJson('/api/members')->json('template.id');
  foreach([['Older','1980-01-01','21000'],['Younger','2000-01-01','10000'],['Missing',null,null]] as [$name,$dob,$zip])
   ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template,'name'=>$name,'email'=>'','phone'=>'','data'=>['BornOn'=>$dob,'PostalCode'=>$zip]]);
  $params=['columns'=>['name','data.BornOn','data.PostalCode'],'sort_by'=>'data.BornOn','sort_direction'=>'desc'];
  $csv=$this->get('/api/members/export.csv?'.http_build_query($params))->assertOk()->streamedContent();
  $this->assertTrue(strpos($csv,'Younger')<strpos($csv,'Older'));
  $this->assertTrue(strpos($csv,'Older')<strpos($csv,'Missing'));
  $params['sort_by']='data.PostalCode';$params['sort_direction']='asc';
  $csv=$this->get('/api/members/export.csv?'.http_build_query($params))->assertOk()->streamedContent();
  $this->assertTrue(strpos($csv,'Younger')<strpos($csv,'Older'));
  $this->postJson('/api/members/export-presets',$params+['name'=>'Visits'])->assertOk()->assertJsonPath('presets.0.sort_by','data.PostalCode')->assertJsonPath('presets.0.sort_direction','asc');
  $params['sort_by']='email';
  $this->getJson('/api/members/export.csv?'.http_build_query($params))->assertUnprocessable();
  $this->postJson('/api/members/export-presets',$params+['name'=>'Bad'])->assertUnprocessable();
 }
 public function test_exports_selected_columns_and_presets_are_scoped(): void {
  $church=Church::create(['name'=>'Export church']);
  $user=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($user);
  $template=$this->getJson('/api/members')->json('template.id');
  ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template,'name'=>'Ada Test','given_name'=>'Ada','first_surname'=>'Test','email'=>'a@example.test','phone'=>'5551234567','data'=>['BornOn'=>'1990-01-02','AddressLine'=>'=HYPERLINK("bad")']]);
  $this->getJson('/api/members')->assertJsonPath('members.0.dob','1990-01-02')->assertJsonMissingPath('members.0.data');
  $columns=['given_name','first_surname','data.BornOn','data.AddressLine'];
  $response=$this->get('/api/members/export.csv?'.http_build_query(['columns'=>$columns]))->assertOk();
  $csv=$response->streamedContent();
  $this->assertStringContainsString('1990-01-02',$csv);
  $this->assertStringContainsString("'=HYPERLINK",$csv);
  $this->assertStringNotContainsString('a@example.test',$csv);
  $preset=$this->postJson('/api/members/export-presets',['name'=>'Visits','columns'=>$columns])->assertOk()->json('presets.0.id');
  $this->postJson('/api/members/export-presets',['name'=>'Visits','columns'=>['name']])->assertOk()->assertJsonCount(1,'presets')->assertJsonPath('presets.0.columns',['name']);
  $this->getJson('/api/members/export.csv?'.http_build_query(['columns'=>['church_id']]))->assertUnprocessable();
  $this->postJson('/api/members/export-presets',['name'=>'Bad','columns'=>['data.unknown']])->assertUnprocessable();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']));
  $this->getJson('/api/members/export-options')->assertOk()->assertJsonCount(0,'presets');
  $this->deleteJson('/api/members/export-presets/'.$preset)->assertNotFound();
  $other=Church::create(['name'=>'Other']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']));
  $otherCsv=$this->get('/api/members/export.csv?'.http_build_query(['columns'=>['name']]))->assertOk()->streamedContent();
  $this->assertStringNotContainsString('Ada',$otherCsv);
  Sanctum::actingAs($user);$this->deleteJson('/api/members/export-presets/'.$preset)->assertNoContent();
 }
}
