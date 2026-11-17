<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Models\Department;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplySuperAdminContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $churchId = $request->header('X-Church-Context');
        $departmentId = $request->header('X-Department-Context');
        $role = $request->header('X-Role-Context');
        $churchRoleId = $request->header('X-Church-Role-Context');
        if (!$churchId && !$departmentId && !$role && !$churchRoleId) {
            return $next($request);
        }

        $actor = $request->user();
        abort_unless($actor?->isSuperAdmin(), 403, 'Only superadmins can switch viewing context.');
        abort_unless($churchId && ctype_digit((string) $churchId), 422, 'A valid church context is required.');
        abort_unless(!$role || in_array($role, ['secretary', 'pastor'], true), 422, 'Invalid role context.');
        abort_if($role && $departmentId, 422, 'Role and department contexts cannot be combined.');
        $church = Church::findOrFail($churchId);
        $churchRole = null;
        if ($churchRoleId) {
            abort_unless(ctype_digit((string)$churchRoleId),422,'Invalid church role.');
            abort_if($role,422,'Choose one role context.');
            $churchRole = \App\Models\ChurchRole::where('church_id',$church->id)->findOrFail($churchRoleId);
            abort_if($departmentId && !in_array($churchRole->base_role,['admin','member'],true),422,'This role does not use departments.');
        }
        $department = null;
        if ($departmentId) {
            abort_unless(ctype_digit((string) $departmentId), 422, 'Invalid department context.');
            $department = Department::where('church_id', $church->id)->findOrFail($departmentId);
        }

        // Keep the actual actor ID for histories/auditing; never persist this role projection.
        $viewer = clone $actor;
        $viewer->role = $churchRole?->base_role ?? ($department ? 'admin' : ($role ?: 'secretary'));
        $viewer->church_role_id = $churchRole?->id;
        $viewer->church_id = $church->id;
        $viewer->department_id = $department?->id;
        $viewer->setRelation('church', $church);
        $viewer->setRelation('department', $department);
        $request->attributes->set('viewing_context', [
            'actor_id' => $actor->id, 'church_id' => $church->id,
            'department_id' => $department?->id, 'role' => $viewer->role, 'church_role_id' => $churchRole?->id,
        ]);
        $guard = Auth::guard('sanctum');
        $guard->setUser($viewer);
        $resolver = $request->getUserResolver();
        $request->setUserResolver(fn () => $viewer);
        try {
            return $next($request);
        } finally {
            $guard->setUser($actor);
            $request->setUserResolver($resolver);
        }
    }
}
