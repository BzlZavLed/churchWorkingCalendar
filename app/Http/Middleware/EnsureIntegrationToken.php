<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.integration.token');
        if (!$expected) {
            Log::error('Integration token missing for request.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'method' => $request->method(),
                'expected' => $expected,
            ]);
            return response()->json(['message' => 'Integration token is not configured.'], 500);
        }

        $token = $this->extractToken($request);
        if (!$token || !hash_equals($expected, $token)) {
            Log::warning('Integration token unauthorized.', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'method' => $request->method(),
                'has_token' => (bool) $token,
            ]);
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        Log::info('Integration token authorized.', [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'method' => $request->method(),
        ]);

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
