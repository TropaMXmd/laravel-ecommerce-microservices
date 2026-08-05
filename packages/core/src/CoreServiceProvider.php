<?php

namespace Ecomstarter\Core;

use Ecomstarter\Core\Http\Middleware\HttpLogMiddleware;
use Ecomstarter\Core\Http\Middleware\TraceIdMiddleware;
use Ecomstarter\Core\Http\Middleware\ValidateJwt;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        // ── Middleware aliases ────────────────────────────────────────────────
        $router->aliasMiddleware('trace.id', TraceIdMiddleware::class);
        $router->aliasMiddleware('http.log', HttpLogMiddleware::class);
        $router->aliasMiddleware('validate.jwt', ValidateJwt::class);

        // ── Publishable assets ────────────────────────────────────────────────
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations/create_http_logs_table.php'
                    => database_path(
                        'migrations/' . date('Y_m_d_His') . '_create_http_logs_table.php'
                    ),
            ], 'core-migrations');

            $this->publishes([
                __DIR__ . '/../config/http_log.php'
                    => config_path('http_log.php'),
            ], 'core-config');
        }

        // ── Merge default config ──────────────────────────────────────────────
        $this->mergeConfigFrom(
            __DIR__ . '/../config/http_log.php',
            'http_log'
        );
    }

    public function register(): void {}
}