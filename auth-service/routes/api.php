<?php


use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Auth Service
|--------------------------------------------------------------------------
| Base URL: http://localhost:8000/api/
| All routes versioned under v1/
| TraceIdMiddleware and HttpLogMiddleware applied globally in bootstrap/app.php
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Health check — no auth ────────────────────────────────────────────────
    Route::get('/health', function () {
        return response()->json([
            'service' => config('app.name'),
            'status'  => 'ok',
            'checks'  => [
                'database'     => checkDbConnection(),
                'passport_key' => file_exists(storage_path('oauth-public.key'))
                    ? 'ok'
                    : 'missing',
            ],
        ]);
    });

    // ── Public — no token required ────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/public-key', [TokenController::class, 'publicKey']);
    });

    // ── Protected — valid Bearer token + active account ───────────────────────
    Route::prefix('auth')
        ->middleware(['auth:api', EnsureUserIsActive::class])
        ->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/me', [AuthController::class, 'updateProfile']);
        });

    // ── Service-to-service — client credentials only ──────────────────────────
    Route::prefix('auth')
        ->middleware(['auth:api', 'scopes:orders.create'])
        ->group(function () {
            Route::post('/introspect', [TokenController::class, 'introspect']);
        });
        
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function checkDbConnection(): string
{
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return 'ok';
    } catch (\Exception) {
        return 'error';
    }
}
