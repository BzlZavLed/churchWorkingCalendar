<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,ChurchRole,Committee,MemberTemplate,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class CommitteeTest extends TestCase {
 use RefreshDatabase;
 private function member(Church $church,string $name): ChurchMember {
  $template=MemberTemplate::firstOrCreate(['church_id'=>$church->id,'version'=>1],['fields'=>[]]);
  return ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template->id,'name'=>$name,'email'=>$name.'@example.test','phone'=>'1234567890','data'=>['private'=>'Not for the committee picker']]);
 }
 public function test_clerk_forms_and_updates_committee_with_own_church_members_and_creator_is_immutable(): void {
  $church=Church::create(['name'=>'Main']);$other=Church::create(['name'=>'Other']);
  $a=$this->member($church,'Ana');$b=$this->member($church,'Ben');$foreign=$this->member($other,'Foreign');
  $clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($clerk);
  $this->getJson('/api/members')->assertOk()->assertJsonCount(2,'members');
  $this->getJson('/api/committees')->assertOk()->assertJsonCount(2,'members')->assertJsonMissingPath('members.0.data')->assertJsonMissingPath('members.0.phone');
  $data=['name'=>'Music committee','objective'=>'Coordinate worship','formation_voted_on'=>now()->toDateString(),'minutes_number'=>'2026-12','member_ids'=>[$a->id,$b->id],'created_by'=>999];
  $result=$this->postJson('/api/committees',$data)->assertCreated()->assertJsonPath('created_by',$clerk->id)->assertJsonPath('members_count',2)->assertJsonPath('revision',1);
  $id=$result->json('id');
  $second=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($second);
  $data['revision']=1;$data['member_ids']=[$a->id];
  $this->patchJson('/api/committees/'.$id,$data)->assertOk()->assertJsonPath('members_count',1)->assertJsonPath('revision',2)->assertJsonPath('created_by',$clerk->id);
  $this->patchJson('/api/committees/'.$id,$data)->assertConflict();
  $data['member_ids']=[$foreign->id];$this->postJson('/api/committees',$data)->assertUnprocessable();
  $data['member_ids']=[];$this->postJson('/api/committees',$data)->assertUnprocessable();
  $data['member_ids']=[$a->id,$a->id];$this->postJson('/api/committees',$data)->assertUnprocessable();
  $data['member_ids']=[$a->id];$data['formation_voted_on']=now()->addDay()->toDateString();$this->postJson('/api/committees',$data)->assertUnprocessable();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']));
  $this->getJson('/api/committees')->assertOk()->assertJsonCount(0,'committees')->assertJsonCount(1,'members');
  $this->patchJson('/api/committees/'.$id,$data)->assertNotFound();
 }
 public function test_module_access_and_action_permission_are_enforced(): void {
  $church=Church::create(['name'=>'Main']);$pastor=User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']);Sanctum::actingAs($pastor);
  $this->getJson('/api/committees')->assertForbidden();$this->postJson('/api/committees',[])->assertForbidden();
  $role=ChurchRole::create(['church_id'=>$church->id,'key'=>'pastor','name'=>'Pastor','base_role'=>'pastor','is_builtin'=>true,'permissions'=>['committees'=>'read'],'capabilities'=>['committees.manage'=>true]]);
  $this->getJson('/api/committees')->assertOk();$this->postJson('/api/committees',[])->assertForbidden();
  $role->update(['permissions'=>['committees'=>'edit']]);$this->postJson('/api/committees',[])->assertUnprocessable();
  $role->update(['capabilities'=>['committees.manage'=>false]]);$this->postJson('/api/committees',[])->assertForbidden();
 }
 public function test_registered_minutes_are_linked_and_external_numbers_remain_supported(): void {
  $church=Church::create(['name'=>'Main']);$other=Church::create(['name'=>'Other']);$member=$this->member($church,'Ana');
  $clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($clerk);
  $base=['meeting_date'=>now()->toDateString(),'planned_start_at'=>now(),'start_at'=>now(),'end_at'=>now(),'minutes_number'=>7,'created_by'=>$clerk->id,'updated_by'=>$clerk->id,'summary_public'=>false,'summary_text'=>'Private minutes text'];
  $meeting=\App\Models\Meeting::create($base+['church_id'=>$church->id]);
  $foreign=\App\Models\Meeting::create($base+['church_id'=>$other->id]);
  $open=\App\Models\Meeting::create(array_replace($base,['church_id'=>$church->id,'minutes_number'=>null,'end_at'=>null]));
  $this->getJson('/api/committees')->assertOk()->assertJsonCount(1,'minutes')->assertJsonPath('minutes.0.minutes_number',7)->assertJsonMissingPath('minutes.0.summary_text');
  $data=['name'=>'Committee','objective'=>'Serve','formation_voted_on'=>now()->toDateString(),'member_ids'=>[$member->id],'formation_meeting_id'=>$meeting->id,'minutes_number'=>'Spoofed'];
  $id=$this->postJson('/api/committees',$data)->assertCreated()->assertJsonPath('formation_meeting_id',$meeting->id)->assertJsonPath('minutes_number','7')->json('id');
  $this->postJson('/api/committees',array_replace($data,['formation_meeting_id'=>$foreign->id]))->assertUnprocessable();
  $this->postJson('/api/committees',array_replace($data,['formation_meeting_id'=>$open->id]))->assertUnprocessable();
  $this->patchJson('/api/committees/'.$id,array_replace($data,['revision'=>1,'formation_meeting_id'=>null,'minutes_number'=>'1995-04']))->assertOk()->assertJsonPath('formation_meeting_id',null)->assertJsonPath('minutes_number','1995-04');
 }

 public function test_results_are_private_downloadable_and_require_write_permission(): void {
  \Illuminate\Support\Facades\Storage::fake('local');
  $church=Church::create(['name'=>'Main']);$other=Church::create(['name'=>'Other']);
  $clerk=User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']);Sanctum::actingAs($clerk);
  $committee=Committee::create(['church_id'=>$church->id,'name'=>'Test','objective'=>'Serve','formation_voted_on'=>now()->toDateString(),'minutes_number'=>'1','created_by'=>$clerk->id]);
  $url='/api/committees/'.$committee->id.'/reports';
  $response=$this->postJson($url,['title'=>'Results','file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('results.csv',"item,result\nvisit,complete\n")])->assertCreated()->assertJsonMissingPath('path')->assertJsonPath('uploaded_by',$clerk->id);
  $report=\App\Models\CommitteeReport::findOrFail($response->json('id'));
  \Illuminate\Support\Facades\Storage::disk('local')->assertExists($report->path);
  $pdf=\Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Committee results</h1>')->output();
  $pdf.='\n%'.str_repeat(' ',6*1024*1024);
  $pdfId=$this->postJson($url,['title'=>'Large PDF','file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('results.pdf',$pdf)])->assertCreated()->json('id');
  $this->get($url.'/'.$pdfId.'/preview')->assertOk()->assertHeader('Content-Type','application/pdf');
  $download=$url.'/'.$report->id.'/download';
  $this->get($download)->assertOk()->assertDownload('results.csv');
  $this->getJson($url.'/'.$report->id.'/preview')->assertOk()->assertJsonPath('type','table')->assertJsonPath('sheets.0.rows.1.1','complete');
  $this->getJson('/api/committees')->assertOk()->assertJsonPath('committees.0.reports.0.title','Large PDF')->assertJsonMissingPath('committees.0.reports.0.path');
  $this->postJson($url,['title'=>'Bad','file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('bad.html','<script>alert(1)</script>')])->assertUnprocessable();
  $this->postJson($url,['title'=>'Bad','file'=>\Illuminate\Http\UploadedFile::fake()->create('large.pdf',20481,'application/pdf')])->assertUnprocessable();
  $role=ChurchRole::create(['church_id'=>$church->id,'key'=>'secretary','name'=>'Clerk','base_role'=>'secretary','is_builtin'=>true,'permissions'=>['committees'=>'read']]);
  $this->get($download)->assertOk();$this->postJson($url,[])->assertForbidden();
  $role->update(['permissions'=>['committees'=>'edit'],'capabilities'=>['committees.results'=>false]]);
  $this->postJson($url,[])->assertForbidden();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']));
  $this->get($download)->assertNotFound();$this->getJson($url.'/'.$report->id.'/preview')->assertNotFound();$this->postJson($url,[])->assertNotFound();
 }

}
