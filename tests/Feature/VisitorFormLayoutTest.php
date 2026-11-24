<?php
namespace Tests\Feature;
use App\Models\{Church,User,ChurchContact,VisitorForm};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class VisitorFormLayoutTest extends TestCase {
 use RefreshDatabase;
 public function test_images_layout_and_answers_are_versioned_and_church_scoped():void {
  Storage::fake('local');
  $church=Church::create(['name'=>'Welcome']);$other=Church::create(['name'=>'Other']);
  $pastor=User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']);Sanctum::actingAs($pastor);
  $asset=$this->postJson('/api/visitor-form/images',['image'=>UploadedFile::fake()->image('banner.png',200,100)])->assertOk()->json('id');
  $this->get('/api/public/churches/'.$church->id.'/visitor-form-images/'.$asset)->assertOk();
  $this->get('/api/public/churches/'.$other->id.'/visitor-form-images/'.$asset)->assertNotFound();
  $field=['id'=>(string)Str::uuid(),'type'=>'text','label'=>'Invited by','required'=>true,'options'=>[],'span'=>6];
  $layout=['title'=>'Welcome','introduction'=>'Please complete','accent'=>'#123456','images'=>[['id'=>(string)Str::uuid(),'source'=>'upload','asset'=>$asset,'position'=>'header','alignment'=>'center','width'=>50,'alt'=>'Welcome banner']]];
  $form=$this->postJson('/api/visitor-form',['base_version'=>0,'fields'=>[$field],'layout'=>$layout])->assertCreated()->assertJsonPath('layout.title','Welcome')->json('id');
  $url='/api/public/churches/'.$church->id.'/visit';
  $this->getJson($url)->assertOk()->assertJsonPath('form.layout.images.0.asset',$asset);
  $this->postJson($url,['name'=>'Test visitor','is_sda'=>false,'sms_consent'=>false,'email_consent'=>false,'visitor_form_id'=>$form,'custom_answers'=>[$field['id']=>'A friend']])->assertCreated();
  $this->postJson('/api/visitor-form',['base_version'=>1,'fields'=>[],'layout'=>['title'=>'New','introduction'=>'','accent'=>'#123456','images'=>[]]])->assertCreated();
  $this->assertSame('Welcome',VisitorForm::find($form)->layout['title']);
  $contact=ChurchContact::where('church_id',$church->id)->first();
  $this->assertSame('A friend',$contact->custom_answers[$field['id']]);
  $this->assertEquals($form,$contact->visitor_form_id);
  $this->getJson('/api/visitor-form')->assertOk()->assertJsonPath('report_fields.0.id',$field['id']);
  $this->getJson('/api/church-contacts')->assertOk()->assertJsonPath('0.visitor_form.layout.title','Welcome');
  Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'pastor']));
  $this->postJson('/api/visitor-form',['base_version'=>0,'fields'=>[],'layout'=>$layout])->assertUnprocessable();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $this->postJson('/api/visitor-form/images',['image'=>UploadedFile::fake()->image('x.png')])->assertForbidden();
 }
}
