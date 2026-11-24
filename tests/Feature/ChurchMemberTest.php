<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,MemberTemplate,User};
use App\Services\MemberDirectorySchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ChurchMemberTest extends TestCase {
 use RefreshDatabase;
 private function context(): Church {
  $church=Church::create(['name'=>'Directory']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));return $church;
 }
 private function payload(): array {
  return ['template_id'=>$this->getJson('/api/members')->assertOk()->json('template.id'),'given_name'=>'Ana','email'=>'ana@example.test','phone'=>'+15551234567','data'=>['BornOn'=>'1990-01-02']];
 }
 private function csv(array $rows,bool $legacyEncoding=false): UploadedFile {
  $stream=fopen('php://temp','r+');fputcsv($stream,MemberDirectorySchema::HEADERS,',','"','');
  foreach($rows as $row)fputcsv($stream,array_map(fn($key)=>$row[$key]??'',MemberDirectorySchema::HEADERS),',','"','');
  rewind($stream);$text=stream_get_contents($stream);fclose($stream);
  return UploadedFile::fake()->createWithContent('directory.csv',$legacyEncoding?mb_convert_encoding($text,'Windows-1252','UTF-8'):$text);
 }
 public function test_directory_details_are_scoped_and_list_does_not_load_private_data(): void {
  $church=$this->context();$payload=$this->payload();
  $payload['data']+=['AddressLine'=>'Private address','PersonId'=>'00001','IsConfidential'=>true];
  $id=$this->postJson('/api/members',$payload)->assertOk()->json('id');
  $this->getJson('/api/members')->assertJsonMissingPath('members.0.data');
  $this->getJson('/api/members/'.$id)->assertOk()->assertJsonPath('member.data.PersonId','00001');
  $other=Church::create(['name'=>'Other']);Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'pastor']));
  $this->getJson('/api/members/'.$id)->assertNotFound();$this->patchJson('/api/members/'.$id,$payload)->assertNotFound();$this->deleteJson('/api/members/'.$id)->assertNotFound();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));
  $this->getJson('/api/members/'.$id)->assertForbidden();$this->postJson('/api/members',$payload)->assertForbidden();
  Sanctum::actingAs(User::factory()->create(['role'=>'superadmin']));
  $this->getJson('/api/members/'.$id,['X-Church-Context'=>(string)$church->id,'X-Role-Context'=>'pastor'])->assertOk();
 }
 public function test_only_four_core_fields_required_and_old_data_preserved(): void {
  $church=$this->context();$old=MemberTemplate::create(['church_id'=>$church->id,'version'=>1,'fields'=>[['id'=>'old-note','label'=>'Old note','type'=>'text','required'=>true,'options'=>[]],['id'=>'old-birth','label'=>'DOB','type'=>'dob','required'=>false,'options'=>[]]]]);
  $member=ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$old->id,'name'=>'Old','email'=>'old@example.test','phone'=>'5551234567','data'=>['old-note'=>'Keep me','old-birth'=>'1990-01-02']]);
  $payload=$this->payload();
  $this->getJson('/api/members/'.$member->id)->assertJsonPath('member.data.BornOn','1990-01-02');
  $this->patchJson('/api/members/'.$member->id,$payload)->assertOk()->assertJsonPath('data.old-note','Keep me');
  foreach(['given_name','email','phone','data'] as $key){$bad=$payload;$bad[$key]=$key==='data'?[]:'';$this->postJson('/api/members',$bad)->assertUnprocessable();}
  $payload['email']='new@example.test';$this->postJson('/api/members',$payload)->assertOk();
  $payload['email']='another@example.test';$payload['data']['BornOn']='2999-01-01';$this->postJson('/api/members',$payload)->assertUnprocessable();
  $this->postJson('/api/members/template',['fields'=>[]])->assertStatus(405);
 }
 public function test_csv_encoding_dates_optional_fields_preview_and_atomic_validation(): void {
  $this->context();
  $download=$this->get('/api/members/template.csv')->assertOk()->streamedContent();
  $this->assertSame(MemberDirectorySchema::HEADERS,str_getcsv(substr(trim($download),3),',','"',''));
  $one=['FirstName'=>'Ana','LastName'=>'Muñoz','Email'=>'ANA@example.test','FamilyPhone'=>'001234567','BornOn'=>'01/02/1990','IsConfidential'=>'N','MobileUnlisted'=>'Y','PostalCode'=>'00123','PersonId'=>'0001'];
  $two=['FirstName'=>'Luis','Email'=>'ANA@example.test','MobilePhone'=>'+15551234567','BornOn'=>'1991-03-04'];
  $this->postJson('/api/members/import',['file'=>$this->csv([$one,$two],true),'preview'=>true])->assertOk()->assertJsonPath('count',2)->assertJsonPath('sample.0.name','Ana Muñoz');
  $this->assertDatabaseCount('church_members',0);
  $bad=$two;unset($bad['BornOn']);
  $this->postJson('/api/members/import',['file'=>$this->csv([$one,$bad]),'preview'=>false])->assertUnprocessable()->assertJsonValidationErrors('row_3');$this->assertDatabaseCount('church_members',0);
  $this->postJson('/api/members/import',['file'=>$this->csv([$one,$two],true),'preview'=>false])->assertOk()->assertJsonPath('created',2);
  $member=ChurchMember::where('source_person_id','0001')->firstOrFail();
  $this->assertSame('001234567',$member->phone);$this->assertSame('00123',$member->data['PostalCode']);$this->assertSame('1990-01-02',$member->data['BornOn']);$this->assertFalse($member->data['IsConfidential']);$this->assertTrue($member->data['MobileUnlisted']);
  $this->postJson('/api/members/import',['file'=>$this->csv([$one]),'preview'=>false])->assertUnprocessable();
  $this->getJson('/api/members/birthdays?date=2026-01-02&frequency=daily&timezone=America/New_York')->assertOk()->assertJsonPath('members.0.name','Ana Muñoz');
 }
 public function test_read_only_member_access_cannot_edit_or_delete(): void {
  $church=$this->context();$payload=$this->payload();$id=$this->postJson('/api/members',$payload)->json('id');
  $role=\App\Models\ChurchRole::create(['church_id'=>$church->id,'key'=>'reader','name'=>'Reader','base_role'=>'pastor','permissions'=>['members'=>'read']]);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'pastor','church_role_id'=>$role->id]));
  $this->getJson('/api/members/'.$id)->assertOk();
  $this->patchJson('/api/members/'.$id,$payload)->assertForbidden();
  $this->deleteJson('/api/members/'.$id)->assertForbidden();
 }
 public function test_import_accepts_reordered_core_columns_and_validates_headers(): void {
  $this->context();
  $text="BornOn,Email,FirstName,WorkPhone\n1990-01-01,member@example.test,Member,5551234567\n";
  $this->postJson('/api/members/import',['file'=>UploadedFile::fake()->createWithContent('core.csv',$text),'preview'=>true])->assertOk()->assertJsonPath('count',1);
  $text="BornOn,Email,FirstName,WorkPhone,Unknown\n1990-01-01,member@example.test,Member,5551234567,no\n";
  $this->postJson('/api/members/import',['file'=>UploadedFile::fake()->createWithContent('invalid.csv',$text),'preview'=>true])->assertUnprocessable()->assertJsonValidationErrors('file');
 }
 public function test_editable_preview_returns_cell_errors_and_corrected_rows_import_atomically(): void {
  $this->context();
  $row=['FirstName'=>'Ana','PersonId'=>'review-1','Email'=>'bad','MobilePhone'=>'','BornOn'=>''];
  $response=$this->postJson('/api/members/import',['file'=>$this->csv([$row]),'preview'=>true,'editable'=>true])->assertOk()->assertJsonPath('valid',false)->assertJsonPath('invalid_count',1);
  $response->assertJsonStructure(['rows'=>[['line','values','errors'=>['Email','MobilePhone','BornOn']]]]);
  $this->assertDatabaseCount('church_members',0);
  $this->postJson('/api/members/import',['file'=>$this->csv([$row]),'preview'=>false,'editable'=>true])->assertUnprocessable();
  $fixed=$response->json('rows.0.values');$fixed['Email']='shared@example.test';$fixed['MobilePhone']='5551234567';$fixed['BornOn']='1990-02-03';
  $this->postJson('/api/members/import',['file'=>$this->csv([$fixed]),'preview'=>true,'editable'=>true])->assertOk()->assertJsonPath('valid',true);
  $this->assertDatabaseCount('church_members',0);
  $this->postJson('/api/members/import',['file'=>$this->csv([$fixed]),'preview'=>false])->assertOk()->assertJsonPath('created',1);
  $this->postJson('/api/members/import',['file'=>$this->csv([$fixed]),'preview'=>true,'editable'=>true])->assertOk()->assertJsonPath('rows.0.duplicate_action','skip')->assertJsonPath('rows.0.duplicate_matches.0.match','person_id');
 }
 public function test_delete_protects_history_and_removes_unreferenced_member(): void {
  $church=$this->context();$payload=$this->payload();$id=$this->postJson('/api/members',$payload)->json('id');
  DB::table('member_attendances')->insert(['church_id'=>$church->id,'church_member_id'=>$id,'attendance_date'=>'2026-11-01','created_at'=>now(),'updated_at'=>now()]);
  $this->deleteJson('/api/members/'.$id)->assertUnprocessable();
  $payload['email']='delete@example.test';$id=$this->postJson('/api/members',$payload)->json('id');
  $this->deleteJson('/api/members/'.$id)->assertNoContent();$this->getJson('/api/members/'.$id)->assertNotFound();
 }
}
