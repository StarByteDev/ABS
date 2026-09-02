<?php

use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePrivateMember;
use App\Http\Middleware\EnsurePulseAccess;
use App\Http\Middleware\EnsurePulseCapability;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureApplicationInstalled::class);
        // Logout is safe to execute without CSRF because it only destroys the current
        // authenticated session. This also prevents stale cached home pages from
        // producing Laravel's 419 Page Expired screen during sign-out.
        $middleware->validateCsrfTokens(except: ['logout']);

        $middleware->alias([
            'role' => EnsureRole::class,
            'account.active' => EnsureAccountActive::class,
            'private.member' => EnsurePrivateMember::class,
            'pulse.access' => EnsurePulseAccess::class,
            'pulse.capability' => EnsurePulseCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Central exception customization can be added here in future revisions.
    })->create();
