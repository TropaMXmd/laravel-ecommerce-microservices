<?php


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
    // Route::prefix('products')->group(function () {
    //     Route::get('/', [ProductController::class, 'index']);
    //     Route::get('/{sku}', [ProductController::class, 'show']);
    // });

    // ── Protected — valid Bearer token + active account ───────────────────────
    Route::middleware('validate.jwt:orders.create')->group(function () {
        Route::post('/products', function () {
            return response()->json(['message' => 'Product created successfully']);
        });
    });


});

// ── Helpers ───────────────────────────────────────────────────────
function checkDbConnection(): string
{
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return 'ok';
    } catch (\Exception) {
        return 'error';
    }
}
