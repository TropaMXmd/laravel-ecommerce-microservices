<?php

use App\Http\Middleware\EnsureUserIsActive;
use Ecomstarter\Core\Http\Middleware\HttpLogMiddleware;
use Ecomstarter\Core\Http\Middleware\TraceIdMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'active'   => EnsureUserIsActive::class,
            'trace.id' => TraceIdMiddleware::class,
            'http.log' => HttpLogMiddleware::class,
            'scopes' => CheckToken::class,
            'scope'  => CheckTokenForAnyScope::class,
        ]);

        $middleware->appendToGroup('api', TraceIdMiddleware::class);
        $middleware->appendToGroup('api', HttpLogMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->is('oauth/*')) {
                return app(\App\Exceptions\Handler::class)->render($request, $e);
            }
        });
    })
    ->create();
