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
                'token_present' => false,
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
                'token_length' => $token ? strlen($token) : 0,
                'expected_length' => strlen($expected),
                'expected_prefix' => substr($expected, 0, 6),
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
