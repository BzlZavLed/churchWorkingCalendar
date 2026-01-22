<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldLog($request, $response)) {
            return $response;
        }

        $action = $this->resolveAction($request);
        $entityType = $this->resolveEntityType($request);
        $entityId = $this->resolveEntityId($request);

        app(AuditLogger::class)->log($request, $action, $entityType, $entityId);

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        return $response->getStatusCode() < 400;
    }

    private function resolveAction(Request $request): string
    {
        $path = trim($request->path(), '/');
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }

        return match ($path) {
            'auth/login' => 'login',
            'auth/logout' => 'logout',
            'auth/register' => 'register',
            'auth/recover' => 'password_reset',
            default => match ($request->method()) {
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => 'action',
            },
        };
    }

    private function resolveEntityType(Request $request): ?string
    {
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));
        if (empty($segments)) {
            return null;
        }
        if ($segments[0] === 'api') {
            array_shift($segments);
        }
        if (isset($segments[0]) && in_array($segments[0], ['superadmin', 'admin', 'secretary', 'auth', 'public'], true)) {
            array_shift($segments);
        }

        return $segments[0] ?? null;
    }

    private function resolveEntityId(Request $request): ?string
    {
        $params = $request->route()?->parameters() ?? [];
        foreach ($params as $value) {
            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));
        $last = end($segments);
        return is_numeric($last) ? (string) $last : null;
    }
}
