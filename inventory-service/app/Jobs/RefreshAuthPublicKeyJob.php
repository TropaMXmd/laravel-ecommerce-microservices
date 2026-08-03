<?php

namespace App\Jobs;

use App\Http\Middleware\ValidateJwt;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proactively refreshes the cached auth-service public key so the
 * request-time reactive refresh (in ValidateJwt) is a rare fallback,
 * not the primary refresh mechanism. Scheduled well inside the cache TTL
 * so a request should never actually observe a cold or stale cache.
 *
 * Deliberately NOT ShouldQueue — see PublishOutboxMessagesJob for why.
 */
class RefreshAuthPublicKeyJob
{
    use Dispatchable;

    public function handle(): void
    {
        try {
            $response = Http::timeout(5)
                ->retry(2, 200)
                ->get(config('services.auth.url') . '/api/v1/auth/public-key');

            if (!$response->successful()) {
                Log::warning('RefreshAuthPublicKeyJob: non-2xx from auth-service', ['status' => $response->status()]);
                return;
            }

            $key = $response->json('data.public_key') ?? $response->json('public_key');

            if ($key) {
                Cache::put(ValidateJwt::CACHE_KEY, $key, ValidateJwt::CACHE_TTL_SECONDS);
            }
        } catch (ConnectionException $e) {
            // Auth-service unreachable — leave the existing cached key in place.
            // The reactive refresh path in ValidateJwt covers us if it turns out stale.
            Log::warning('RefreshAuthPublicKeyJob: could not reach auth-service: ' . $e->getMessage());
        }
    }
}

// Register in bootstrap/app.php withSchedule():
// Schedule::job(new RefreshAuthPublicKeyJob)->hourly();
// (cache TTL is 24h, so hourly refresh gives huge margin before staleness is even possible)
