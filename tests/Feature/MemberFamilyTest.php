<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class MemberFamilyTest extends TestCase {
 use RefreshDatabase;
 public function test_grouping_and_moving_selected_people_preserves_other_members_and_data(): void {
  $church=Church::create(['name'=>'Family church']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $template=$this->getJson('/api/members')->json('template.id');
  $people=[];
  foreach(['001','001','002'] as $i=>$family)$people[]=ChurchMember::create(['church_id'=>$church->id,'member_template_id'=>$template,'name'=>'Person '.$i,'email'=>'','phone'=>'','data'=>['FamilyId'=>$family,'AddressLine'=>'Original address']]);
  $this->getJson('/api/members/families')->assertOk()->assertJsonPath('families.0.id','001')->assertJsonPath('families.0.count',2);
  $this->getJson('/api/members')->assertJsonPath('members.0.family_id','001');
  $result=$this->postJson('/api/members/families/move',['member_ids'=>[$people[0]->id,$people[2]->id],'mode'=>'new'])->assertOk()->assertJsonPath('updated',2)->json();
  $this->assertStringStartsWith('F-',$result['family_id']);
  $this->assertSame($result['family_id'],$people[0]->fresh()->data['FamilyId']);
  $this->assertSame($result['family_id'],$people[2]->fresh()->data['FamilyId']);
  $this->assertSame('001',$people[1]->fresh()->data['FamilyId']);
  $this->assertSame('Original address',$people[0]->fresh()->data['AddressLine']);
  $this->postJson('/api/members/families/move',['member_ids'=>[$people[0]->id],'mode'=>'existing','family_id'=>'001'])->assertOk();
  $this->postJson('/api/members/families/move',['member_ids'=>[$people[0]->id],'mode'=>'existing','family_id'=>'unknown'])->assertUnprocessable();
  $other=Church::create(['name'=>'Other']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']));
  $this->postJson('/api/members/families/move',['member_ids'=>[$people[0]->id],'mode'=>'none'])->assertNotFound();
  $this->getJson('/api/members/families')->assertJsonCount(0,'families');
 }
 public function test_manual_creation_can_start_or_join_a_family(): void {
  $church=Church::create(['name'=>'Manual','organization_code'=>'00123']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $template=$this->getJson('/api/members')->json('template.id');
  $payload=['template_id'=>$template,'given_name'=>'First','email'=>'a@example.test','phone'=>'5551234567','data'=>['BornOn'=>'1990-01-01','OrgCode'=>'wrong','OrgName'=>'Other church'],];
  $first=$this->postJson('/api/members',$payload)->assertOk()->json();
  $this->assertStringStartsWith('F-',$first['data']['FamilyId']);
  $this->assertSame('00123',$first['data']['OrgCode']);
  $this->assertSame('Manual',$first['data']['OrgName']);
  $payload['given_name']='Second';$payload['family_mode']='existing';$payload['family_id']=$first['data']['FamilyId'];
  $this->postJson('/api/members',$payload)->assertOk()->assertJsonPath('data.FamilyId',$payload['family_id']);
  $payload['family_id']='unknown';$this->postJson('/api/members',$payload)->assertUnprocessable();
  $this->assertDatabaseCount('church_members',2);
 }
}
