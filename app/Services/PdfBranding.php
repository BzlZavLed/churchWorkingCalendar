<?php
namespace App\Services;
use App\Models\Church;
use Illuminate\Support\Facades\Storage;
class PdfBranding {
 public function logo(?Church $church): ?string {
  $path=$church?->visitor_logo_path;
  if(!$path || !Storage::disk('local')->exists($path))return null;
  $bytes=Storage::disk('local')->get($path);
  $image=@imagecreatefromstring($bytes);
  if(!$image)return null;
  ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
  return 'data:image/png;base64,'.base64_encode($png);
 }
}
