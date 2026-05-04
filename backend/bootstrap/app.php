<?php

use App\Http\Middleware\VerifyConversionInternalSignature;
use App\Http\Middleware\VerifyGamificationWebhookSignature;
use App\Http\Middleware\VerifyIdentityWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ThrottleRequests::class.':api',
        ]);
        $middleware->alias([
            'identity.webhook' => VerifyIdentityWebhookSignature::class,
            'conversion.internal' => VerifyConversionInternalSignature::class,
            // ADR-009 Phase B inbound — py-service → 母艦 gamification webhook
            'gamification.webhook' => VerifyGamificationWebhookSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry reports exceptions when SENTRY_LARAVEL_DSN env is set; no-op otherwise.
        Integration::handles($exceptions);

        // 殺 `Route [login] not defined` — API request 一律回 JSON 401，不 redirect
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }
            return null;
        });
    })->create();
