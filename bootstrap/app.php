<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::prefix('api')->group(base_path('routes/webhooks.php'));

            Route::middleware(['web', 'auth', 'verified', \App\Http\Middleware\EnsureAdmin::class])
                ->prefix('admin')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \App\Http\Middleware\AuthenticateMerchantApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return null;
            }
            if ($request instanceof \Illuminate\Http\Request
                && str_starts_with($request->path(), 'api/webhooks/')) {
                \Illuminate\Support\Facades\Log::error('Webhook endpoint exception', [
                    'path' => $request->path(),
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Webhook processing failed. Please retry.',
                ], 500);
            }
        });
    })->create();
