<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return redirect()->route('superadmin.login');
        }

        if (!$user->isSuperAdmin()) {
            abort(403, 'Superadmin access required.');
        }

        return $next($request);
    }
}
