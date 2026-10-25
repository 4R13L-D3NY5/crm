<?php

use App\Shared\Http\Middleware\RequireOrganizationAdmin;
use App\Shared\Http\Middleware\SetCurrentOrganization;
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
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'social/comments/webhook',
        ]);
        $middleware->alias([
            'current.organization' => SetCurrentOrganization::class,
            'require.admin' => RequireOrganizationAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            $cookieName = config('session.cookie');
            $cookieSessionId = $request->cookies->get($cookieName);
            $sessionId = $request->hasSession() ? $request->session()->getId() : $cookieSessionId;

            if ($cookieSessionId && \Illuminate\Support\Facades\Cache::has("revoked_session:{$cookieSessionId}")) {
                \Illuminate\Support\Facades\Cache::forget("revoked_session:{$cookieSessionId}");

                return response()->json([
                    'message' => 'Tu sesión ha sido cerrada porque se inició sesión en otro dispositivo.',
                    'code' => 'SESSION_REVOKED_ANOTHER_DEVICE',
                ], 401);
            }

            if ($sessionId && \Illuminate\Support\Facades\Cache::has("revoked_session:{$sessionId}")) {
                \Illuminate\Support\Facades\Cache::forget("revoked_session:{$sessionId}");

                return response()->json([
                    'message' => 'Tu sesión ha sido cerrada porque se inició sesión en otro dispositivo.',
                    'code' => 'SESSION_REVOKED_ANOTHER_DEVICE',
                ], 401);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
