<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class WhatsAppPreferencesController extends Controller {
 public function show(Request $request){return response()->json([
  'enabled'=>(bool)$request->user()->whatsapp_assistant_enabled,
  'system_enabled'=>(bool)config('whatsapp.enabled'),
  // Do not claim enrollment or working chat before the transport is connected.
  'ready'=>false,
 ]);}
 public function update(Request $request){
  $data=$request->validate(['enabled'=>['required','boolean']]);
  $request->user()->forceFill(['whatsapp_assistant_enabled'=>$data['enabled']])->save();
  return $this->show($request);
 }
}
