<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchContact,User,VisitorForm};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitorFormTest extends TestCase
{
    use RefreshDatabase;
    public function test_logo_upload_is_pastor_scoped_validated_and_publicly_viewable(): void {
        \Illuminate\Support\Facades\Storage::fake('local');
        $church=Church::create(['name'=>'Main']);
        $other=Church::create(['name'=>'Other']);
        $pastor=User::factory()->create(['role'=>'pastor','church_id'=>$church->id]);
        Sanctum::actingAs($pastor);
        $image=fn()=>\Illuminate\Http\UploadedFile::fake()->image('logo.png',120,120);
        $this->postJson('/api/visitor-form/logo',['logo'=>$image()])->assertOk()->assertJsonStructure(['logo_url']);
        $this->patchJson('/api/visitor-form/logo',['alignment'=>'right'])->assertOk()->assertJsonPath('logo_alignment','right');
        $this->getJson('/api/visitor-form')->assertJsonPath('logo_alignment','right');
        $this->getJson('/api/public/churches/'.$church->id.'/visit')->assertJsonPath('logo_alignment','right');
        $this->patchJson('/api/visitor-form/logo',['alignment'=>'invalid'])->assertUnprocessable();
        $this->assertSame('center',$other->fresh()->visitor_logo_alignment);
        $old=$church->fresh()->visitor_logo_path;
        $this->getJson('/api/visitor-form')->assertOk()->assertJsonPath('logo_url',$church->fresh()->visitorLogoUrl());
        $this->get('/api/public/churches/'.$church->id.'/visitor-logo')->assertOk()->assertHeader('Content-Type','image/png');
        $this->get('/api/public/churches/'.$other->id.'/visitor-logo')->assertNotFound();
        $this->postJson('/api/visitor-form/logo',['logo'=>\Illuminate\Http\UploadedFile::fake()->create('unsafe.svg',1,'image/svg+xml')])->assertUnprocessable();
        $this->postJson('/api/visitor-form/logo',['logo'=>$image()->size(2049)])->assertUnprocessable();
        $this->postJson('/api/visitor-form/logo',['logo'=>$image()])->assertOk();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($old);
        $this->assertNull($other->fresh()->visitor_logo_path);
        Sanctum::actingAs(User::factory()->create(['role'=>'secretary','church_id'=>$church->id]));
        $this->postJson('/api/visitor-form/logo',['logo'=>$image()])->assertForbidden();
        $this->deleteJson('/api/visitor-form/logo')->assertForbidden();
        $this->patchJson('/api/visitor-form/logo',['alignment'=>'left'])->assertForbidden();
        Sanctum::actingAs($pastor);
        $this->deleteJson('/api/visitor-form/logo')->assertOk()->assertJsonPath('logo_url',null);
        $this->get('/api/public/churches/'.$church->id.'/visitor-logo')->assertNotFound();
    }

    private function field(string $type='text', bool $required=false): array {
        return ['id'=>(string)Str::uuid(),'type'=>$type,'label'=>'Question '.$type,'required'=>$required,'options'=>$type==='select'?['One','Two']:[]];
    }
    private function payload(): array { return ['name'=>'Visitor','is_sda'=>false,'sms_consent'=>false,'email_consent'=>false]; }

    public function test_only_pastor_publishes_and_updates_existing_answers(): void {
        $church=Church::create(['name'=>'Main']);
        $field=$this->field();
        foreach(['secretary','admin','member','superadmin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>$role]));
            $this->postJson('/api/visitor-form',['base_version'=>0,'fields'=>[$field]])->assertForbidden();
        }
        Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']));
        $id=$this->postJson('/api/visitor-form',['base_version'=>0,'fields'=>[$field]])->assertCreated()->json('id');
        $contact=ChurchContact::create(['church_id'=>$church->id,'name'=>'Existing','phone'=>'555','visitor_form_id'=>$id,'custom_answers'=>[$field['id']=>'Original']]);
        $this->getJson('/api/church-contacts')->assertOk()->assertJsonMissingPath('0.consent_token')->assertJsonPath('0.custom_answers.'.$field['id'],'Original');
        $this->postJson('/api/church-contacts',$this->payload())->assertForbidden();
        $this->patchJson("/api/church-contacts/{$contact->id}/consent",['channel'=>'sms','consent_confirmed'=>true])->assertForbidden();
        $this->patchJson("/api/church-contacts/{$contact->id}",['custom_answers'=>[]])->assertUnprocessable();
        $this->assertSame('Original',$contact->fresh()->custom_answers[$field['id']]);
        $newField=$this->field('checkbox',true);
        $newId=$this->postJson('/api/visitor-form',['base_version'=>1,'fields'=>[$newField]])->assertCreated()->json('id');
        $update=['visitor_form_id'=>$newId,'custom_answers'=>[$newField['id']=>true]];
        $this->patchJson("/api/church-contacts/{$contact->id}",$update)->assertOk()->assertJsonPath('custom_answers.'.$newField['id'],true)->assertJsonMissingPath('consent_token');
        $this->assertSame('Original',$contact->fresh()->original_custom_answers[$field['id']]);
        $this->assertSame('Original',$contact->fresh()->custom_answers[$field['id']]);
        $this->patchJson("/api/church-contacts/{$contact->id}",['sms_consent'=>true])->assertUnprocessable();
        $this->patchJson("/api/church-contacts/{$contact->id}",['sms_consent'=>true,'consent_confirmed'=>true])->assertOk()->assertJsonPath('sms_consent',true);
        $this->patchJson("/api/church-contacts/{$contact->id}",['sms_consent'=>false])->assertOk()->assertJsonPath('sms_consent',false)->assertJsonPath('sms_consented_at',null);
        $this->patchJson("/api/church-contacts/{$contact->id}",['visitor_form_id'=>$newId,'custom_answers'=>[$newField['id']=>'not a boolean']])->assertUnprocessable();
        $other=Church::create(['name'=>'Other']);
        $otherContact=ChurchContact::create(['church_id'=>$other->id,'name'=>'Other visitor']);
        $this->patchJson("/api/church-contacts/{$otherContact->id}",$update)->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
        $this->patchJson("/api/church-contacts/{$contact->id}",$update)->assertForbidden();

    }

    public function test_versioned_answers_validate_and_survive_rename_removal_and_stale_forms(): void {
        $church=Church::create(['name'=>'Main']);
        Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']));
        $fields=array_map(fn($t)=>$this->field($t,true),['text','textarea','number','date','select','checkbox','label']);
        $id=$this->postJson('/api/visitor-form',['base_version'=>0,'fields'=>$fields])->assertCreated()->json('id');
        $url="/api/public/churches/{$church->id}/visit";
        $this->getJson($url)->assertJsonPath('form.id',$id)->assertJsonCount(7,'form.fields');
        $this->postJson($url,$this->payload())->assertUnprocessable();
        $answers=array_combine(array_column(array_slice($fields,0,6),'id'),['hello','long text','0','2026-11-17','Two',true]);
        $base=$this->payload()+['visitor_form_id'=>$id,'custom_answers'=>$answers];
        $bad=$base;$bad['custom_answers'][$fields[4]['id']]='Unknown';
        $this->postJson($url,$bad)->assertUnprocessable();
        $bad=$base;$bad['custom_answers']['forged']='value';
        $this->postJson($url,$bad)->assertUnprocessable();
        $this->postJson($url,$base)->assertCreated();
        $fields[0]['label']='Renamed';
        $this->postJson('/api/visitor-form',['base_version'=>1,'fields'=>[$fields[0]]])->assertCreated();
        $this->postJson('/api/visitor-form',['base_version'=>1,'fields'=>[]])->assertConflict();
        $this->postJson($url,$base)->assertCreated(); // A form already opened remains valid.
        $fields[0]['type']='number';
        $this->postJson('/api/visitor-form',['base_version'=>2,'fields'=>[$fields[0]]])->assertUnprocessable();
        $this->getJson('/api/church-contacts')->assertJsonPath('0.visitor_form.fields.0.label','Question text')->assertJsonPath('0.custom_answers.'.$fields[2]['id'],'0');
        $other=Church::create(['name'=>'Other']);
        $this->postJson("/api/public/churches/{$other->id}/visit",$base)->assertUnprocessable();
    }

    public function test_clerk_can_create_pastor_but_department_admin_cannot_promote_self(): void {
        $church=Church::create(['name'=>'Main']);
        Sanctum::actingAs(User::factory()->create(['role'=>'secretary','church_id'=>$church->id]));
        $this->postJson('/api/secretary/users',['name'=>'Pastor','email'=>'pastor@example.test','password'=>'test-password','role'=>'pastor'])->assertCreated()->assertJsonPath('department_id',null);
        $admin=User::factory()->create(['role'=>'admin','church_id'=>$church->id]);
        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/users/{$admin->id}",['role'=>'pastor'])->assertForbidden();
    }
}
