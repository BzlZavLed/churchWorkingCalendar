<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.integration.token');
        if (!$expected) {
            return response()->json(['message' => 'Integration token is not configured.'], 500);
        }

        $token = $this->extractToken($request);
        if (!$token || !hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            return $bearer;
        }

        $headerToken = $request->header('X-Integration-Token');
        return $headerToken ?: null;
    }
}
