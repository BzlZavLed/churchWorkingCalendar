<?php
namespace App\Http\Controllers;
use App\Models\{Church,ChurchRole,Department,User};
use App\Services\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class ChurchRoleController extends Controller {
 private function seed(Church $church): void {
  foreach(ModuleAccess::BASES as $base)ChurchRole::firstOrCreate(['church_id'=>$church->id,'key'=>$base],['name'=>['secretary'=>'Secretaría','pastor'=>'Pastor','admin'=>'Administrador de departamento','member'=>'Miembro de departamento'][$base],'base_role'=>$base,'permissions'=>[],'is_builtin'=>true]);
 }
 public function index(Church $church,ModuleAccess $access) {
  $this->seed($church);
  $roles=ChurchRole::where('church_id',$church->id)->orderBy('id')->get()->map(function($role)use($access,$church){$role->setAttribute('effective_permissions',array_replace($access->defaults($role->base_role),$role->permissions));$role->setAttribute('effective_capabilities',array_replace($access->capabilityDefaults($role->base_role),$role->capabilities ?? []));$role->setAttribute('default_permissions',$access->defaults($role->base_role));$role->setAttribute('default_capabilities',$access->capabilityDefaults($role->base_role));$role->setAttribute('users_count',User::where('church_id',$church->id)->where(fn($q)=>$q->where('church_role_id',$role->id)->when($role->is_builtin,fn($q)=>$q->orWhere(fn($q)=>$q->whereNull('church_role_id')->where('role',$role->base_role))))->count());return $role;});
  return ['roles'=>$roles,'modules'=>ModuleAccess::MODULES,'capability_catalog'=>ModuleAccess::CAPABILITIES,'users'=>User::where('church_id',$church->id)->where('role','!=','superadmin')->get(['id','name','email','role','church_role_id','department_id'])];
 }
 public function store(Request $request,Church $church) {
  $this->seed($church);
  $data=$request->validate(['name'=>['required','string','max:100',Rule::unique('church_roles')->where('church_id',$church->id)],'base_role'=>['required',Rule::in(ModuleAccess::BASES)]]);
  return response()->json(ChurchRole::create($data+['church_id'=>$church->id,'key'=>'custom_'.\Illuminate\Support\Str::uuid(),'permissions'=>array_fill_keys(array_keys(ModuleAccess::MODULES),'none')]),201);
 }
 public function update(Request $request,Church $church,ChurchRole $role) {
  abort_unless($role->church_id===$church->id,404);
  $data=$request->validate(['reset_defaults'=>['sometimes','boolean'],'capabilities'=>['sometimes','array'],'capabilities.*'=>['boolean'],'revision'=>['required','integer'],'name'=>['required','string','max:100',Rule::unique('church_roles')->where('church_id',$church->id)->ignore($role->id)],'permissions'=>['required','array:'.implode(',',array_keys(ModuleAccess::MODULES))],...collect(ModuleAccess::MODULES)->mapWithKeys(fn($v,$key)=>['permissions.'.$key=>['required','in:none,read,edit']])->all()]);
  if (array_diff(array_keys($data['capabilities'] ?? []),array_keys(ModuleAccess::CAPABILITIES))) throw \Illuminate\Validation\ValidationException::withMessages(['capabilities'=>['Unknown action.']]);
  return DB::transaction(function()use($role,$data){$role=ChurchRole::whereKey($role->id)->lockForUpdate()->firstOrFail();abort_unless($role->revision===$data['revision'],409,'Role changed. Reload before saving.');$role->update(['name'=>$data['name'],'permissions'=>($data['reset_defaults']??false)?[]:$data['permissions'],'capabilities'=>($data['reset_defaults']??false)?[]:($data['capabilities']??$role->capabilities),'revision'=>$role->revision+1]);return $role;});
 }
 public function assign(Request $request,Church $church,User $user) {
  abort_unless($user->church_id===$church->id && !$user->isSuperAdmin(),404);
  $data=$request->validate(['church_role_id'=>['required','integer'],'department_id'=>['nullable','integer']]);
  $role=ChurchRole::where('church_id',$church->id)->findOrFail($data['church_role_id']);
  $department=null;
  if(in_array($role->base_role,['admin','member'],true)){$department=Department::where('church_id',$church->id)->findOrFail($data['department_id']??$user->department_id);}
  $user->update(['role'=>$role->base_role,'church_role_id'=>$role->id,'department_id'=>$department?->id]);return $user;
 }
 public function current(Request $request,ModuleAccess $access) {
  $user=$request->user();$role=$access->role($user);
  return ['user'=>$user->load(['church','department']),'permissions'=>$access->permissions($user),'capabilities'=>collect(ModuleAccess::CAPABILITIES)->mapWithKeys(fn($v,$key)=>[$key=>$access->allows($user,$key)])->all(),'role_name'=>$role?->name,'role_id'=>$role?->id,'base_role'=>$user->role,'modules'=>ModuleAccess::MODULES];
 }
}
