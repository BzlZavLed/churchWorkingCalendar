<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,MemberImportDraft,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class MemberImportDraftTest extends TestCase {
 use RefreshDatabase;
 private function context(): array {$church=Church::create(['name'=>'Draft church']);$user=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($user);return [$church,$user];}
 private function row(int $line,array $replace=[],bool $authorize=false): array {return ['line'=>$line,'values'=>array_replace(['FirstName'=>'Member '.$line,'Email'=>'shared@example.test','MobilePhone'=>'5551234567','BornOn'=>'1990-01-02','PersonId'=>'source-'.$line],$replace),'authorize_missing'=>$authorize];}
 public function test_duplicate_is_skipped_or_explicitly_updated_without_replacing_empty_fields(): void {
  [$church]=$this->context();$first=$this->postJson('/api/members/import-drafts',['rows'=>[$this->row(2,['City'=>'Original city'])]])->assertOk()->json();
  $this->postJson('/api/members/import-drafts/'.$first['draft_id'].'/commit',['revision'=>1])->assertOk();$member=ChurchMember::firstOrFail();
  $duplicate=$this->row(3,['PersonId'=>'source-2','FirstName'=>'Changed','City'=>'']);$new=$this->row(4);
  $draft=$this->postJson('/api/members/import-drafts',['rows'=>[$duplicate,$new]])->assertOk()->assertJsonPath('rows.0.duplicate_matches.0.id',$member->id)->assertJsonPath('rows.0.duplicate_action','skip')->json();
  $this->postJson('/api/members/import-drafts/'.$draft['draft_id'].'/commit',['revision'=>1])->assertOk()->assertJsonPath('created',1)->assertJsonPath('skipped',1);$this->assertSame('Member 2',$member->fresh()->name);
  $duplicate['duplicate_action']='update';$duplicate['duplicate_target']=$member->id;
  $draft=$this->postJson('/api/members/import-drafts',['rows'=>[$duplicate]])->assertOk()->json();
  $this->postJson('/api/members/import-drafts/'.$draft['draft_id'].'/commit',['revision'=>1])->assertOk()->assertJsonPath('updated',1);
  $this->assertSame('Changed',$member->fresh()->name);$this->assertSame('Original city',$member->fresh()->data['City']);$this->assertDatabaseCount('church_members',2);
 }
 public function test_save_for_later_keeps_only_errors_without_losing_review_rows(): void {
  $this->context();
  $rows=array_map(fn($line)=>$this->row($line,$line<26?['Email'=>'']:[]),range(2,76));
  $draft=$this->postJson('/api/members/import-drafts',['rows'=>$rows])->assertOk()->json();
  $url='/api/members/import-drafts/'.$draft['draft_id'];
  $saved=$this->putJson($url,['revision'=>$draft['revision'],'pending_only'=>true,'rows'=>$rows])->assertOk()->assertJsonPath('saved_count',24)->assertJsonPath('count',75)->assertJsonPath('eligible_count',51)->json();
  $this->getJson($url)->assertJsonPath('count',24);
  $this->getJson('/api/members/import-drafts')->assertJsonPath('drafts.0.count',24);
  $this->assertDatabaseCount('church_members',0);
  // Import still receives the complete browser review, then leaves only errors.
  $ready=$this->putJson($url,['revision'=>$saved['revision'],'pending_only'=>false,'rows'=>$rows])->assertOk()->json();
  $result=$this->postJson($url.'/commit',['revision'=>$ready['revision']])->assertOk()->assertJsonPath('created',51)->assertJsonPath('remaining',24)->json();
  $corrected=array_map(fn($line)=>$this->row($line),range(2,25));
  $this->putJson($url,['revision'=>$result['review']['revision'],'pending_only'=>true,'rows'=>$corrected])->assertOk()->assertJsonPath('saved_count',0)->assertJsonPath('count',24);
  $this->getJson('/api/members/import-drafts')->assertJsonCount(0,'drafts');
  $this->assertDatabaseCount('church_members',51);
 }
 public function test_draft_deletion_is_owner_scoped_and_does_not_delete_members(): void {
  [$church,$user]=$this->context();
  $draft=$this->postJson('/api/members/import-drafts',['rows'=>[$this->row(2),$this->row(3,['Email'=>''])]])->json();
  $url='/api/members/import-drafts/'.$draft['draft_id'];
  $this->postJson($url.'/commit',['revision'=>1])->assertOk();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $this->deleteJson($url)->assertNotFound();
  Sanctum::actingAs($user);
  $this->deleteJson($url)->assertNoContent();
  $this->getJson($url)->assertNotFound();
  $this->assertDatabaseCount('church_members',1);
  $this->assertDatabaseCount('member_import_drafts',0);
 }
 public function test_partial_import_persists_pending_and_retries_do_not_duplicate(): void {
  $this->context();$draft=$this->postJson('/api/members/import-drafts',['rows'=>[$this->row(2),$this->row(3,['Email'=>''])]])->assertOk()->assertJsonPath('eligible_count',1)->json();
  $this->assertDatabaseCount('church_members',0);
  $this->assertStringNotContainsString('shared@example.test',MemberImportDraft::find($draft['draft_id'])->getRawOriginal('rows'));
  $url='/api/members/import-drafts/'.$draft['draft_id'];
  $result=$this->postJson($url.'/commit',['revision'=>$draft['revision']])->assertOk()->assertJsonPath('created',1)->assertJsonPath('remaining',1)->json();
  $this->getJson($url)->assertOk()->assertJsonPath('count',1)->assertJsonPath('rows.0.line',3);
  $this->postJson($url.'/commit',['revision'=>$draft['revision']])->assertConflict();$this->assertDatabaseCount('church_members',1);
  $this->getJson('/api/members/import-drafts')->assertJsonCount(1,'drafts');
  $next=$this->putJson($url,['revision'=>$result['review']['revision'],'rows'=>[$this->row(3)]])->assertOk()->json();
  $this->postJson($url.'/commit',['revision'=>$next['revision']])->assertOk()->assertJsonPath('created',1)->assertJsonPath('remaining',0);
  $this->getJson('/api/members/import-drafts')->assertJsonCount(0,'drafts');$this->assertDatabaseCount('church_members',2);
 }
 public function test_authorizations_are_per_row_and_only_allow_missing_values(): void {
  $this->context();$draft=$this->postJson('/api/members/import-drafts',['rows'=>[$this->row(2,['FirstName'=>'','Email'=>'','MobilePhone'=>'','BornOn'=>''],true),$this->row(3,['Email'=>'']),$this->row(4,['Email'=>'bad-email'],true)]])->assertOk()->assertJsonPath('rows.0.authorize_missing',true)->assertJsonPath('rows.2.can_authorize',false)->assertJsonPath('eligible_count',1)->json();
  $this->postJson('/api/members/import-drafts/'.$draft['draft_id'].'/commit',['revision'=>1])->assertOk()->assertJsonPath('created',1)->assertJsonPath('remaining',2);
  $member=ChurchMember::firstOrFail();$this->assertSame(['name','email','phone','dob'],$member->missing_fields);$this->assertNotNull($member->missing_data_authorization['user_id']);
  $this->getJson('/api/members')->assertJsonCount(4,'members.0.missing_fields');
  $template=$this->getJson('/api/members')->json('template.id');
  $payload=['template_id'=>$template,'given_name'=>'Completed','email'=>'filled@example.test','phone'=>'5551234567','data'=>['BornOn'=>'1990-01-02']];
  $this->patchJson('/api/members/'.$member->id,$payload)->assertOk()->assertJsonPath('missing_fields',[]);
  $payload['email']='';$this->patchJson('/api/members/'.$member->id,$payload)->assertUnprocessable();
 }
 public function test_drafts_are_private_to_church_and_user_and_require_manage_permission(): void {
  [$church,$user]=$this->context();$id=$this->postJson('/api/members/import-drafts',['rows'=>[$this->row(2)]])->json('draft_id');
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $this->getJson('/api/members/import-drafts')->assertJsonCount(0,'drafts');$this->getJson('/api/members/import-drafts/'.$id)->assertNotFound();$this->postJson('/api/members/import-drafts/'.$id.'/commit',['revision'=>1])->assertNotFound();
  Sanctum::actingAs(User::factory()->create(['church_id'=>Church::create(['name'=>'Other'])->id,'role'=>'pastor']));$this->getJson('/api/members/import-drafts/'.$id)->assertNotFound();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));$this->getJson('/api/members/import-drafts')->assertForbidden();$this->postJson('/api/members/import-drafts',['rows'=>[$this->row(2)]])->assertForbidden();
 }
 public function test_missing_data_cannot_be_waived_through_regular_creation_or_duplicate_id(): void {
  $this->context();$template=$this->getJson('/api/members')->json('template.id');$this->postJson('/api/members',['template_id'=>$template,'given_name'=>'Name','email'=>'','phone'=>'','data'=>[],'missing_fields'=>[],'missing_data_authorization'=>['fields'=>['email','phone','dob']]])->assertUnprocessable();
  $draft=$this->postJson('/api/members/import-drafts',['rows'=>[$this->row(2),$this->row(3,['PersonId'=>'source-2','Email'=>''],true)]])->assertOk()->assertJsonPath('rows.1.can_authorize',false)->json();
  $this->postJson('/api/members/import-drafts/'.$draft['draft_id'].'/commit',['revision'=>1])->assertJsonPath('created',1)->assertJsonPath('remaining',1);
 }
}
