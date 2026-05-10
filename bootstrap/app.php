<?php

use App\Http\Middleware\EnsureSanctumApiTokenCan;
use App\Http\Middleware\EnsureSiteAccess;
use App\Http\Middleware\RememberExpandedSite;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Middleware\HandleCors;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetSecurityHeaders::class);
        $middleware->web(append: [
            ConvertEmptyStringsToNull::class,
            RequireTwoFactor::class,
        ]);

        // Prepend CORS handling to the API group so preflight OPTIONS requests
        // to the public tracking / form / inbox endpoints are answered before
        // any authentication or throttle middleware can reject them.
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        $middleware->alias([
            'site.access' => EnsureSiteAccess::class,
            'expand.site.sidebar' => RememberExpandedSite::class,
            'sanctum.token.can' => EnsureSanctumApiTokenCan::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Forward exceptions to Sentry when the SDK is installed and SENTRY_DSN is set.
        // Install: composer require sentry/sentry-laravel
        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
