<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecretary
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $module = $request->attributes->get('access_module');
        if ($user && in_array($module, ['users','departments'], true) && $user->hasCapability($module.'.manage')) return $next($request);

        if ($user && $request->isMethod('get') && $request->attributes->get('access_module')) return $next($request);

        if ($user === null || $user->role !== 'secretary') {
            abort(403, 'Secretary access required.');
        }

        return $next($request);
    }
}
