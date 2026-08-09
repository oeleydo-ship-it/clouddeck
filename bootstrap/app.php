<?php

use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsureImpersonationIntegrity;
use App\Http\Middleware\EnsureImpersonationWriteAccess;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\PreserveActiveTab;
use App\Http\Middleware\RequireFeature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->web(prepend: [EnsureInstalled::class], append: [
            PreserveActiveTab::class,
            EnsureImpersonationIntegrity::class,
            EnsureImpersonationWriteAccess::class,
        ]);
        $middleware->append(EnsureNotSuspended::class);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'admin' => EnsureSuperAdmin::class,
            'feature' => RequireFeature::class,
            'verified' => EnsureEmailIsVerifiedWhenRequired::class,
        ]);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
