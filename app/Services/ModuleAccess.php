<?php
namespace App\Services;
use App\Models\{ChurchRole,User};
class ModuleAccess {
 public const MODULES=[
  'calendar'=>['es'=>'Calendario','en'=>'Calendar','path'=>'/calendar'],
  'reports'=>['es'=>'Reportes','en'=>'Reports','path'=>'/reports'],
  'objectives'=>['es'=>'Objetivos','en'=>'Objectives','path'=>'/objectives'],
  'budget'=>['es'=>'Presupuesto','en'=>'Budget','path'=>'/budget'],
  'inventory'=>['es'=>'Inventario','en'=>'Inventory','path'=>'/inventory'],
  'meetings'=>['es'=>'Juntas','en'=>'Meetings','path'=>'/secretary/meetings'],
  'meeting_points'=>['es'=>'Puntos de junta','en'=>'Meeting points','path'=>'/meeting-points'],
  'visitors'=>['es'=>'Visitas y formulario','en'=>'Visitors and form','path'=>'/greeting/contacts'],
  'greeting'=>['es'=>'Bienvenida / Quiosco','en'=>'Greeting / Kiosk','path'=>'/greeting'],
  'members'=>['es'=>'Miembros','en'=>'Members','path'=>'/members'],
  'departments'=>['es'=>'Departamentos','en'=>'Departments','path'=>'/secretary/departments'],
  'users'=>['es'=>'Usuarios','en'=>'Users','path'=>'/secretary/users'],
 ];
 // These presets are the original workflows. Overrides belong only to a church role.
 public const CAPABILITIES = [
  'calendar.create'=>['module'=>'calendar','es'=>'Crear eventos departamentales','en'=>'Create department events','roles'=>['admin','member']],
  'calendar.edit'=>['module'=>'calendar','es'=>'Editar eventos departamentales','en'=>'Edit department events','roles'=>['admin','member']],
  'calendar.all_departments'=>['module'=>'calendar','es'=>'Crear y editar para cualquier departamento','en'=>'Create and edit for any department','roles'=>['admin']],
  'calendar.review'=>['module'=>'calendar','es'=>'Revisar, aprobar y reabrir eventos','en'=>'Review, approve and reopen events','roles'=>['secretary']],
  'calendar.conference'=>['module'=>'calendar','es'=>'Gestionar y publicar eventos de asociación','en'=>'Manage and publish conference events','roles'=>['secretary']],
  'meetings.manage'=>['module'=>'meetings','es'=>'Gestionar agenda, reunión en vivo y actas','en'=>'Manage agenda, live meeting and minutes','roles'=>['secretary']],
  'meeting_points.submit'=>['module'=>'meeting_points','es'=>'Presentar y actualizar puntos del departamento','en'=>'Submit and update department points','roles'=>['admin']],
  'visitors.configure'=>['module'=>'visitors','es'=>'Diseñar formulario público','en'=>'Design public form','roles'=>['pastor']],
  'visitors.edit'=>['module'=>'visitors','es'=>'Editar datos y preferencias de visitas','en'=>'Edit visitor data and preferences','roles'=>['pastor']],
  'visitors.contact'=>['module'=>'visitors','es'=>'Gestionar permisos de contacto','en'=>'Manage contact consent','roles'=>['secretary']],
  'greeting.register'=>['module'=>'greeting','es'=>'Registrar visitas (predeterminado: Secretaría y Bienvenida)','en'=>'Register visitors (default: Clerk and Greeting)','roles'=>['secretary']],
  'members.manage'=>['module'=>'members','es'=>'Gestionar plantilla y miembros','en'=>'Manage template and members','roles'=>['pastor']],
  'objectives.manage'=>['module'=>'objectives','es'=>'Gestionar objetivos propios','en'=>'Manage own department objectives','roles'=>['admin','member']],
  'inventory.manage'=>['module'=>'inventory','es'=>'Gestionar inventario propio','en'=>'Manage own department inventory','roles'=>['admin']],
  'budget.publish'=>['module'=>'budget','es'=>'Publicar asignaciones de toda la iglesia (predeterminado: solo Tesorería)','en'=>'Publish church allocations (default: Treasury only)','roles'=>['admin']],
  'budget.manage'=>['module'=>'budget','es'=>'Registrar movimientos del departamento','en'=>'Record department movements','roles'=>['admin']],
  'users.manage'=>['module'=>'users','es'=>'Gestionar usuarios de la iglesia','en'=>'Manage church users','roles'=>['secretary']],
  'departments.manage'=>['module'=>'departments','es'=>'Gestionar departamentos de la iglesia','en'=>'Manage church departments','roles'=>['secretary']],
 ];
 public function capabilityDefaults(string $base): array {
  return array_map(fn($definition)=>in_array($base,$definition['roles'],true), self::CAPABILITIES);
 }
 public function capabilities(User $user): array {
  if ($user->isSuperAdmin()) return array_fill_keys(array_keys(self::CAPABILITIES),true);
  $role=$this->role($user);
  if (!$user->church_id || ($user->church_role_id && !$role)) return array_fill_keys(array_keys(self::CAPABILITIES),false);
  $defaults=$this->capabilityDefaults($user->role);
  $defaults['budget.publish']=$user->isAdmin() && (bool) $user->department?->is_treasury;
  if ($user->isGreetingDepartmentUser() && !$user->isPastor()) {$defaults['visitors.contact']=true;$defaults['greeting.register']=true;}
  return array_replace($defaults,$role?->capabilities ?? []);
 }
 public function allows(User $user,string $capability): bool {
  $definition=self::CAPABILITIES[$capability] ?? null;
  return $definition && $this->write($user,$definition['module']) && ($this->capabilities($user)[$capability] ?? false);
 }
 public const BASES=['secretary','pastor','admin','member'];
 public function defaults(string $base, bool $greeter=false): array {
  $p=array_fill_keys(array_keys(self::MODULES),'none');
  if($base==='secretary') return array_replace($p,['calendar'=>'edit','reports'=>'edit','objectives'=>'read','budget'=>'read','inventory'=>'read','meetings'=>'edit','visitors'=>'edit','greeting'=>'edit','departments'=>'edit','users'=>'edit']);
  if($base==='pastor') return array_replace($p,['visitors'=>'edit','members'=>'edit']);
  if(in_array($base,['admin','member'],true)) {
   $p=array_replace($p,['calendar'=>'edit','reports'=>'edit','objectives'=>'edit','budget'=>$base==='admin'?'edit':'read','meeting_points'=>$base==='admin'?'edit':'read']);
   if($base==='admin') $p=array_replace($p,['inventory'=>'edit','visitors'=>'read']);
   if($greeter) $p=array_replace(array_fill_keys(array_keys(self::MODULES),'none'),['greeting'=>'edit','visitors'=>'edit','budget'=>$base==='admin'?'edit':'read','meeting_points'=>$base==='admin'?'edit':'read']);
  }
  return $p;
 }
 public function role(User $user): ?ChurchRole {
  if(!$user->church_id || $user->isSuperAdmin())return null;
  if($user->church_role_id) return ChurchRole::where('church_id',$user->church_id)->where('base_role',$user->role)->find($user->church_role_id);
  return ChurchRole::where('church_id',$user->church_id)->where('is_builtin',true)->where('key',$user->role)->first();
 }
 public function permissions(User $user): array {
  if($user->isSuperAdmin())return array_fill_keys(array_keys(self::MODULES),'edit');
  if(!$user->church_id)return array_fill_keys(array_keys(self::MODULES),'none');
  $role=$this->role($user);
  if($user->church_role_id && !$role)return array_fill_keys(array_keys(self::MODULES),'none');
  return array_replace($this->defaults($user->role,$user->isGreetingDepartmentUser()),$role?->permissions ?? []);
 }
 public function level(User $user,string $module): string {return $this->permissions($user)[$module]??'none';}
 public function read(User $user,string $module): bool {return $this->level($user,$module)!=='none';}
 public function write(User $user,string $module): bool {return $this->level($user,$module)==='edit';}
 public function routeModule(string $path): ?string {
  $path=preg_replace('#^api/#','',trim($path,'/'));
  if(str_starts_with($path,'reports/'))return 'reports';
  if(preg_match('#^(events|conference-events|notes|calendar)(/|$)#',$path))return 'calendar';
  if(preg_match('#^objectives(/|$)#',$path))return 'objectives';
  if(preg_match('#^budget(/|$)#',$path))return 'budget';
  if(preg_match('#^inventory(/|$)#',$path))return 'inventory';
  if(preg_match('#^members(/|$)#',$path))return 'members';
  if(preg_match('#^(church-contacts|visitor-form)(/|$)#',$path))return 'visitors';
  if(preg_match('#^(admin|secretary)/users(/|$)#',$path))return 'users';
  if(preg_match('#^(admin|secretary)/departments(/|$)#',$path))return 'departments';
  if(preg_match('#^invitations(/|$)#',$path))return 'users';
  if(preg_match('#^(meetings|meeting-points)(/|$)#',$path))return 'meetings';
  return null;
 }
}
