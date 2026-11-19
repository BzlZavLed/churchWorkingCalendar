<?php
namespace Tests\Feature;
use App\Models\{Church,ChurchMember,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ChurchMemberTest extends TestCase {
    use RefreshDatabase;
    private function field(string $type, bool $required=false): array {
        return ['id'=>(string)Str::uuid(),'label'=>'Field '.$type,'type'=>$type,'required'=>$required,'options'=>$type==='select'?['One','Two']:[]];
    }
    private function setupPastor(): array {
        $church=Church::create(['name'=>'Members church']);
        $pastor=User::factory()->create(['church_id'=>$church->id,'role'=>'pastor']);
        Sanctum::actingAs($pastor); return [$church,$pastor];
    }
    private function template(array $fields=[],int $version=0): array {
        return $this->postJson('/api/members/template',['base_version'=>$version,'fields'=>$fields])->assertCreated()->json();
    }
    private function csv(array $template,array $rows): UploadedFile {
        $stream=fopen('php://temp','r+');
        $model=\App\Models\MemberTemplate::findOrFail($template['id']);
        fputcsv($stream,app(\App\Services\MemberRecords::class)->headers($model),',','"','');
        foreach($rows as $row) fputcsv($stream,$row,',','"','');
        rewind($stream);$content=stream_get_contents($stream);fclose($stream);
        return UploadedFile::fake()->createWithContent('members.csv',$content);
    }
    public function test_members_are_church_scoped_including_drilldown(): void {
        [$church,$pastor]=$this->setupPastor(); $template=$this->template();
        $payload=['template_id'=>$template['id'],'name'=>'Member','email'=>'MEMBER@example.test','phone'=>'+1 555 123 4567','data'=>[]];
        $id=$this->postJson('/api/members',$payload)->assertOk()->assertJsonPath('email','member@example.test')->json('id');
        $this->getJson('/api/members')->assertOk()->assertJsonCount(1,'members');
        $this->postJson('/api/members',$payload)->assertUnprocessable();
        $other=Church::create(['name'=>'Other']);
        Sanctum::actingAs(User::factory()->create(['role'=>'pastor','church_id'=>$other->id]));
        $this->getJson('/api/members')->assertOk()->assertJsonCount(0,'members');
        $this->patchJson('/api/members/'.$id,$payload)->assertNotFound();
        foreach(['admin','member','superadmin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role'=>$role,'church_id'=>$church->id]));
            $this->getJson('/api/members')->assertForbidden();
            $this->postJson('/api/members/template',['base_version'=>0,'fields'=>[]])->assertForbidden();
            $this->postJson('/api/members',$payload)->assertForbidden();
            $this->patchJson('/api/members/'.$id,$payload)->assertForbidden();
            $this->get('/api/members/template.csv')->assertForbidden();
            $this->postJson('/api/members/import',[])->assertForbidden();
        }
        $this->getJson('/api/members',['X-Church-Context'=>(string)$church->id,'X-Role-Context'=>'pastor'])->assertOk()->assertJsonCount(1,'members');
    }
    public function test_typed_validation_and_template_changes_preserve_old_values(): void {
        $this->setupPastor();
        $fields=array_map(fn($type)=>$this->field($type,true),['text','textarea','email','phone','dob','date','number','checkbox','select']);
        $template=$this->template($fields);
        $values=['Text','Long text','extra@example.test','+15551234','1990-01-02','2026-01-01',12.5,false,'One'];
        $data=array_combine(array_column($fields,'id'),$values);
        $payload=['template_id'=>$template['id'],'name'=>'Member','email'=>'one@example.test','phone'=>'5551234567','data'=>$data];
        $id=$this->postJson('/api/members',$payload)->assertOk()->json('id');
        foreach(['email'=>'','phone'=>'','data'=>[]] as $key=>$value) $this->postJson('/api/members',array_replace($payload,[$key=>$value]))->assertUnprocessable();
        foreach([2=>'bad-email',4=>now()->addDay()->format('Y-m-d'),6=>'NaN',7=>'maybe',8=>'invalid'] as $index=>$bad) {
            $invalid=$payload; $invalid['data'][$fields[$index]['id']]=$bad;
            $this->patchJson('/api/members/'.$id,$invalid)->assertUnprocessable();
        }
        $changed=$fields; $changed[0]['type']='number';
        $this->postJson('/api/members/template',['base_version'=>1,'fields'=>$changed])->assertUnprocessable();
        $next=$this->template([],1);
        $this->patchJson('/api/members/'.$id,$payload)->assertConflict();
        $this->patchJson('/api/members/'.$id,array_replace($payload,['template_id'=>$next['id'],'data'=>[],'name'=>'Updated']))->assertOk()->assertJsonPath('data.'.$fields[0]['id'],'Text');
        $this->postJson('/api/members/template',['base_version'=>1,'fields'=>[]])->assertConflict();
    }
    public function test_csv_template_preview_atomic_import_duplicates_and_stale_templates(): void {
        $this->setupPastor(); $checkbox=$this->field('checkbox');$dob=$this->field('dob');$template=$this->template([$checkbox,$dob]);
        $download=$this->get('/api/members/template.csv')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFName,Email,Phone,",$download);
        $this->assertStringNotContainsString('member_template',$download);
        $this->assertStringNotContainsString('[',$download);
        $rows=[['Member One','one@example.test','+1 5551234','yes','1990-01-02'],['Member Two','two@example.test','001234567','no','']];
        $this->postJson('/api/members/import',['file'=>$this->csv($template,$rows),'preview'=>true])->assertOk()->assertJsonPath('count',2);
        $this->assertDatabaseCount('church_members',0);
        $bad=$rows; $bad[1][1]='bad-email';
        $this->postJson('/api/members/import',['file'=>$this->csv($template,$bad),'preview'=>false])->assertUnprocessable()->assertJsonValidationErrors('row_3');
        $this->assertDatabaseCount('church_members',0);
        $duplicates=$rows; $duplicates[1][1]='ONE@example.test';
        $this->postJson('/api/members/import',['file'=>$this->csv($template,$duplicates),'preview'=>false])->assertUnprocessable();
        $this->postJson('/api/members/import',['file'=>$this->csv($template,$rows),'preview'=>false])->assertOk()->assertJsonPath('created',2);
        $this->assertDatabaseCount('church_members',2);
        $this->assertSame('001234567',ChurchMember::where('email','two@example.test')->first()->phone);
        $this->assertFalse(ChurchMember::where('email','two@example.test')->first()->data[$checkbox['id']]);
        $this->postJson('/api/members/import',['file'=>$this->csv($template,$rows),'preview'=>false])->assertUnprocessable();
        $this->template([$checkbox,$dob,$this->field('text')],1);
        $this->postJson('/api/members/import',['file'=>$this->csv($template,$rows),'preview'=>true])->assertUnprocessable();
    }
}
