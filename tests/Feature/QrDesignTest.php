<?php
namespace Tests\Feature;
use App\Models\{Church,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class QrDesignTest extends TestCase {
 use RefreshDatabase;
 public function test_designs_are_separate_and_church_scoped():void {
  $church=Church::create(['name'=>'QR']);$other=Church::create(['name'=>'Other']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $design=['format'=>'a4','sdaDesign'=>true,'sdaColor'=>'#336699','landscape'=>false,'background'=>'#ffffff','elements'=>[['type'=>'qr','x'=>25,'y'=>40,'width'=>50,'size'=>22,'color'=>'#000000','align'=>'center','centerLogo'=>false,'removeWhiteBackground'=>true]]];
  $this->putJson('/api/qr-designs/visitors',$design)->assertOk();
  $this->getJson('/api/qr-designs/visitors')->assertOk()->assertJsonPath('design.elements.0.removeWhiteBackground',true)->assertJsonPath('design.format','a4')->assertJsonPath('design.sdaDesign',true)->assertJsonPath('design.sdaColor','#336699');
  $this->getJson('/api/qr-designs/attendance')->assertOk()->assertJsonPath('design',null);
  $this->putJson('/api/qr-designs/attendance',array_replace($design,['format'=>'letter']))->assertOk();
  $this->putJson('/api/qr-designs/visitors',array_replace($design,['elements'=>[]]))->assertUnprocessable();
  $this->getJson('/api/qr-designs/other')->assertNotFound();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$other->id,'role'=>'secretary']));
  $this->getJson('/api/qr-designs/visitors')->assertOk()->assertJsonPath('design',null);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));
  $this->putJson('/api/qr-designs/visitors',$design)->assertForbidden();
 }
}
