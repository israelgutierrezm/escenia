<?php

declare(strict_types=1);

use App\Http\ApiExceptionMapper;
use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ShareUserContext;
use App\Infrastructure\Logging\RequestContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First-party SPA auth (Sanctum stateful cookie mode).
        $middleware->statefulApi();

        $middleware->api(prepend: [
            AssignRequestContext::class,
        ]);

        $middleware->api(append: [
            'throttle:api',
        ]);

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.required' => RequireTenant::class,
            'share.user' => ShareUserContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Consistent JSON error envelope for the API. Never leak stack traces.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            [$status, $code, $message, $errors] = ApiExceptionMapper::map($e, (bool) config('app.debug'));

            $payload = [
                'message' => $message,
                'error_code' => $code,
                'request_id' => app(RequestContext::class)->requestId(),
            ];

            if ($errors !== null) {
                $payload['errors'] = $errors;
            }

            return response()->json($payload, $status);
        });
    })->create();
