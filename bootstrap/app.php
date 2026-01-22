<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSecretary;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureSuperAdminWeb;
use App\Http\Middleware\EnsureIntegrationToken;
use App\Http\Middleware\AuditLogMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', AuditLogMiddleware::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'secretary' => EnsureSecretary::class,
            'superadmin' => EnsureSuperAdmin::class,
            'superadmin.web' => EnsureSuperAdminWeb::class,
            'integration.token' => EnsureIntegrationToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
