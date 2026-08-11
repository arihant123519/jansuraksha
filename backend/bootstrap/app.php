<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Token-based (Bearer) auth only — no SPA cookie/session auth, so we
        // intentionally do NOT enable statefulApi(); it would apply the web
        // middleware group (session + CSRF) to API requests from stateful
        // domains and break token logins with "CSRF token mismatch".
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn($request) => $request->expectsJson() || $request->is('api/*')
        );

        // For unexpected server errors on API routes, return a clean, stable
        // JSON envelope instead of leaking a stack trace. A reference id is
        // logged so the underlying error can be traced from the response.
        $exceptions->render(function (\Throwable $e, $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null; // fall through to default handling
            }

            // Let Laravel's built-in handling deal with HTTP/validation/auth
            // exceptions (they already produce correct status codes + JSON).
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return null;
            }

            $ref = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\Log::error('Unhandled API exception', [
                'ref'    => $ref,
                'uri'    => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'error'  => $e->getMessage(),
                'class'  => get_class($e),
            ]);

            return response()->json([
                'message'   => 'An unexpected server error occurred. Please try again.',
                'reference' => $ref,
            ], 500);
        });
    })
    ->create();

// use Illuminate\Foundation\Application;
// use Illuminate\Foundation\Configuration\Exceptions;
// use Illuminate\Foundation\Configuration\Middleware;

// return Application::configure(basePath: dirname(__DIR__))
//     ->withRouting(
//         web: __DIR__.'/../routes/web.php',
//         commands: __DIR__.'/../routes/console.php',
//         health: '/up',
//     )
//     ->withMiddleware(function (Middleware $middleware): void {
//         //
//     })
//     ->withExceptions(function (Exceptions $exceptions): void {
//         //
//     })->create();
