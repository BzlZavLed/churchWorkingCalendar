<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $module = $request->attributes->get('access_module');
        if ($user && in_array($module, ['users','departments'], true) && $user->hasCapability($module.'.manage')) return $next($request);

        if ($user && $request->isMethod('get') && $request->attributes->get('access_module')) return $next($request);

        if ($user === null || !in_array($user->role, ['admin', 'superadmin'], true)) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
