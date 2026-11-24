<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
use App\Services\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class PageActivityController extends Controller {
 private const PAGES=[
  '/calendar'=>'events','/objectives'=>'objectives','/reports'=>'reports','/reports/visitors'=>'church-contacts',
  '/meeting-points'=>'meeting-points','/attendance-manager'=>'attendance','/greeting'=>'attendance','/greeting/attendance'=>'attendance',
  '/greeting/contacts'=>'church-contacts','/greeting/form-builder'=>'visitor-form','/members'=>'members','/members/new'=>'members',
  '/admin/users'=>'users','/secretary/users'=>'users','/secretary/departments'=>'departments','/secretary/meetings'=>'meetings',
  '/secretary/settings'=>'settings','/budget'=>'budget','/inventory'=>'inventory','/committees'=>'committees',
  '/whatsapp-settings'=>'whatsapp','/whatsapp-tools'=>'whatsapp','/no-access'=>'other',
  '/superadmin/churches'=>'churches','/superadmin/departments'=>'departments','/superadmin/calendars'=>'calendar',
  '/superadmin/users'=>'users','/superadmin/meetings'=>'meetings','/superadmin/roles'=>'roles',
  '/superadmin/settings'=>'settings','/superadmin/email-activity'=>'settings','/superadmin/whatsapp-tools'=>'whatsapp',
  '/superadmin/module-context'=>'other',
 ];
 public function store(Request $request) {
  $data=$request->validate(['path'=>['required','string','max:100']]);$path=$data['path'];
  $type=self::PAGES[$path]??null;
  if(!$type && preg_match('#^/members/[0-9]+$#D',$path)){$type='members';$path='/members/:id';}
  if(!$type)throw ValidationException::withMessages(['path'=>['Unsupported page.']]);
  $user=$request->user();$context=$request->attributes->get('viewing_context');
  if(str_starts_with($path,'/superadmin/') && !$user->isSuperAdmin())abort(403);
  if(in_array($path,['/secretary/settings','/whatsapp-tools'],true) && !$user->isSecretary() && !$user->isSuperAdmin())abort(403);
  if($path==='/greeting/form-builder' && !$user->hasCapability('visitors.configure'))abort(403);
  $module=match($type){'events','calendar'=>'calendar','church-contacts','visitor-form'=>'visitors','attendance'=>'greeting','meeting-points'=>'meeting_points',default=>$type};
  if(isset(ModuleAccess::MODULES[$module]) && !$user->isSuperAdmin() && !app(ModuleAccess::class)->read($user,$module))abort(403);
  AuditLog::create(['user_id'=>$user->id,'action'=>'page_view','entity_type'=>$type,'route'=>$path,'method'=>'GET','path'=>$path,'metadata'=>['church_id'=>$context['church_id']??$user->church_id,'viewing_context'=>$context],'created_at'=>now()]);
  return response()->noContent();
 }
}
