<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'credential',
        'invite_code',
    ];

    public function log(Request $request, string $action, ?string $entityType = null, ?string $entityId = null, array $metadata = []): void
    {
        $payload = $this->sanitizePayload($request->all());
        $metadata = array_merge([
            'payload' => $payload,
        ], $metadata);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'route' => optional($request->route())->getName(),
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function sanitizePayload(array $payload): array
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
