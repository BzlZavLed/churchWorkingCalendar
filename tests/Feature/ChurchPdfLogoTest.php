<?php
namespace Tests\Feature;
use App\Models\{Church,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class ChurchPdfLogoTest extends TestCase {
 use RefreshDatabase;
 public function test_clerk_upload_is_scoped_and_logo_is_available_for_pdfs(): void {
  Storage::fake('local');
  $church=Church::create(['name'=>'Logo church']);$other=Church::create(['name'=>'Other']);
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $this->postJson('/api/secretary/church-logo',['logo'=>UploadedFile::fake()->image('logo.png',400,120)])->assertOk();
  $church->refresh();$this->assertNotNull($church->visitor_logo_path);$this->assertNull($other->fresh()->visitor_logo_path);
  $logo=app(\App\Services\PdfBranding::class)->logo($church);$this->assertStringStartsWith('data:image/png;base64,',$logo);
  $html=view('exports.branding',['churchLogo'=>$logo])->render();$this->assertStringContainsString($logo,$html);
  $this->getJson('/api/secretary/integrations')->assertOk()->assertJsonPath('logo_url',$church->visitorLogoUrl());
  $this->postJson('/api/secretary/church-logo',['logo'=>UploadedFile::fake()->create('bad.txt',1,'text/plain')])->assertUnprocessable();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'member']));
  $this->deleteJson('/api/secretary/church-logo')->assertForbidden();
  Sanctum::actingAs(User::factory()->create(['church_id'=>$church->id,'role'=>'secretary']));
  $this->deleteJson('/api/secretary/church-logo')->assertOk();$this->assertNull(app(\App\Services\PdfBranding::class)->logo($church->fresh()));
 }
}
