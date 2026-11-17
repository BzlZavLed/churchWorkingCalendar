<?php
namespace App\Http\Middleware;
use App\Services\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
class EnsureModuleAccess {
 public function handle(Request $request,Closure $next) {
  $user=$request->user(); if(!$user || $user->isSuperAdmin())return $next($request);
  $access=app(ModuleAccess::class);$module=$access->routeModule($request->path());
  if(!$module)return $next($request);
  $read=in_array($request->method(),['GET','HEAD','OPTIONS'],true);
  $allowed=$read?$access->read($user,$module):$access->write($user,$module);
  // Shared meeting endpoints serve either sidebar module, but preserve action-level policies.
  if($module==='meetings') {
   if($read)$allowed=$allowed||$access->read($user,'meeting_points');
   elseif(preg_match('#^api/(meetings/[^/]+/points|meeting-points/[^/]+(?:/notes)?)$#',$request->path()))$allowed=$allowed||$access->write($user,'meeting_points');
  }
  if(!$read && $request->filled('meeting_point_id'))$allowed=$allowed && $access->allows($user,'meetings.manage');
  if(!$read && $request->filled('inventory'))$allowed=$allowed && $access->allows($user,'inventory.manage');
  if($request->is('api/church-contacts') && $request->isMethod('post'))$allowed=$access->write($user,'greeting')||$allowed;
  if($request->is('api/visitor-form') && $read)$allowed=$access->read($user,'greeting')||$allowed;
  abort_unless($allowed,403,'Your role does not have '.($read?'access':'edit access').' to this module.');
  $request->attributes->set('access_module',$module);
  return $next($request);
 }
}
