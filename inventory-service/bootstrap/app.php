<?php

use App\Http\Middleware\ValidateJwt;
use App\Jobs\PublishOutboxMessagesJob;
use App\Jobs\RefreshAuthPublicKeyJob;
use App\Jobs\ReleaseExpiredReservationsJob;
use Ecomstarter\Core\Http\Middleware\HttpLogMiddleware;
use Illuminate\Console\Scheduling\Schedule;
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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
           'valid.token'   => ValidateJwt::class
           ]);
        $middleware->appendToGroup('api', HttpLogMiddleware::class);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new ReleaseExpiredReservationsJob())->everyMinute();
        $schedule->job(new PublishOutboxMessagesJob())->everyFiveSeconds()->withoutOverlapping();
        $schedule->job(new RefreshAuthPublicKeyJob())->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
