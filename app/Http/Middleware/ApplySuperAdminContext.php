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
        if (!$churchId && !$departmentId) {
            return $next($request);
        }

        $actor = $request->user();
        abort_unless($actor?->isSuperAdmin(), 403, 'Only superadmins can switch viewing context.');
        abort_unless($churchId && ctype_digit((string) $churchId), 422, 'A valid church context is required.');
        $church = Church::findOrFail($churchId);
        $department = null;
        if ($departmentId) {
            abort_unless(ctype_digit((string) $departmentId), 422, 'Invalid department context.');
            $department = Department::where('church_id', $church->id)->findOrFail($departmentId);
        }

        // Keep the actual actor ID for histories/auditing; never persist this role projection.
        $viewer = clone $actor;
        $viewer->role = $department ? 'admin' : 'secretary';
        $viewer->church_id = $church->id;
        $viewer->department_id = $department?->id;
        $viewer->setRelation('church', $church);
        $viewer->setRelation('department', $department);
        $request->attributes->set('viewing_context', [
            'actor_id' => $actor->id, 'church_id' => $church->id,
            'department_id' => $department?->id, 'role' => $viewer->role,
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
