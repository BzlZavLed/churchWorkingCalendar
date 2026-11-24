<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SuperAdminEmailActivityController extends Controller {
 public function index(Request $request) {
  abort_unless($request->user()?->isSuperAdmin(),403);
  $data=$request->validate(['church_id'=>['nullable','integer'],'status'=>['nullable','in:accepted,failed,throttled,simulated'],'from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d','after_or_equal:from']]);
  return DB::table('email_activity as e')->leftJoin('churches as c','c.id','=','e.church_id')->leftJoin('users as a','a.id','=','e.actor_id')
   ->when($data['church_id']??null,fn($q,$v)=>$q->where('e.church_id',$v))->when($data['status']??null,fn($q,$v)=>$q->where('e.status',$v))
   ->when($data['from']??null,fn($q,$v)=>$q->whereDate('e.created_at','>=',$v))->when($data['to']??null,fn($q,$v)=>$q->whereDate('e.created_at','<=',$v))
   ->orderByDesc('e.id')->paginate(30,['e.id','e.recipient_email','e.kind','e.status','e.created_at','c.name as church_name','a.name as actor_name']);
 }
}
