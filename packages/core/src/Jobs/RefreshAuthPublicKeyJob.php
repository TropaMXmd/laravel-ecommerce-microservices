<?php

namespace Ecomstarter\Core\Jobs;

use Ecomstarter\Core\Http\Middleware\ValidateJwt;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proactively refreshes the cached auth-service public key so the
 * request-time reactive refresh (in ValidateJwt) is a rare fallback, not
 * the primary refresh mechanism. Scheduled well inside the cache TTL so a
 * request should never actually observe a cold or stale cache.
 *
 * Deliberately NOT ShouldQueue — no queue:work process runs in this
 * project, so a queued job here would silently never execute.
 *
 * Shared across every token-consuming service (inventory, order,
 * notification). Register per-service, since scheduling isn't something
 * the package can do on a consuming app's behalf:
 *
 *   $schedule->job(new \Ecomstarter\Core\Jobs\RefreshAuthPublicKeyJob())->hourly();
 */
class RefreshAuthPublicKeyJob
{
    use Dispatchable;

    public function handle(): void
    {
        try {
            $response = Http::timeout(5)
                ->retry(2, 200)
                ->get(rtrim(config('core.auth.base_url'), '/') . '/api/v1/auth/public-key');

            if (!$response->successful()) {
                Log::warning(config('app.name') . '.RefreshAuthPublicKeyJob: non-2xx from auth-service', [
                    'status' => $response->status(),
                ]);
                return;
            }

            $key = $response->json('public_key') ?? $response->json('data.public_key');

            if ($key) {
                Cache::put(ValidateJwt::CACHE_KEY, $key, ValidateJwt::CACHE_TTL_SECONDS);
                Log::info(config('app.name') . '.RefreshAuthPublicKeyJob: public key refreshed and cached.');
            } else {
                Log::warning(config('app.name') . '.RefreshAuthPublicKeyJob: response missing public_key field.');
            }
        } catch (ConnectionException $e) {
            // Auth-service unreachable — leave the existing cached key in
            // place. The reactive refresh path in ValidateJwt covers us if
            // it turns out to actually be stale.
            Log::warning(config('app.name') . '.RefreshAuthPublicKeyJob: could not reach auth-service: ' . $e->getMessage());
        }
    }
}