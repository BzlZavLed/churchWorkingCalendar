<?php
namespace App\Http\Controllers;
use App\Services\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class QrDesignController extends Controller {
 private function scope(Request $r,string $kind,bool $write=false):int {
  abort_unless(in_array($kind,['visitors','attendance']),404);
  $u=$r->user();$module=$kind==='visitors'?'visitors':'greeting';
  abort_unless($u?->church_id && !$u->isSuperAdmin() && app(ModuleAccess::class)->read($u,$module),403);
  if($write)abort_unless(app(ModuleAccess::class)->write($u,$module),403);
  return $u->church_id;
 }
 public function show(Request $r,string $kind){
  $id=$this->scope($r,$kind);$row=DB::table('qr_designs')->where('church_id',$id)->where('kind',$kind)->first();
  return ['design'=>$row?json_decode($row->design):null,'can_save'=>app(ModuleAccess::class)->write($r->user(),$kind==='visitors'?'visitors':'greeting')];
 }
 public function save(Request $r,string $kind){
  $id=$this->scope($r,$kind,true);
  $d=$r->validate([
   'sdaDesign'=>'sometimes|boolean','sdaColor'=>['sometimes','regex:/^#[0-9a-fA-F]{6}$/'],'format'=>'required|in:a4,letter','landscape'=>'required|boolean','background'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
   'elements'=>'required|array|min:1|max:15','elements.*.type'=>'required|in:qr,logo,text',
   'elements.*.x'=>'required|numeric|between:0,100','elements.*.y'=>'required|numeric|between:0,100',
   'elements.*.width'=>'required|numeric|between:5,100','elements.*.text'=>'nullable|string|max:500',
   'elements.*.size'=>'required|numeric|between:10,60','elements.*.color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
   'elements.*.removeWhiteBackground'=>'sometimes|boolean','elements.*.align'=>'required|in:left,center,right','elements.*.centerLogo'=>'required|boolean',
  ]);
  abort_unless(collect($d['elements'])->where('type','qr')->count()===1,422,'One QR code is required.');
  DB::table('qr_designs')->updateOrInsert(['church_id'=>$id,'kind'=>$kind],['design'=>json_encode($d),'updated_at'=>now(),'created_at'=>now()]);
  return ['saved'=>true];
 }
}
