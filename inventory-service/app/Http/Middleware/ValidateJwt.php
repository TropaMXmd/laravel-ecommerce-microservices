<?php

namespace App\Http\Middleware;

use Closure;
use Ecomstarter\Core\Response\ApiResponseTrait;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the access token locally using the Auth Service's RS256 public key.
 * The key itself is fetched once from Auth Service's public-key endpoint and
 * cached in Redis — so in steady state this middleware still makes zero
 * network calls per request, it just doesn't rely on a shared Docker volume.
 */
class ValidateJwt
{
    use ApiResponseTrait;

    public const CACHE_KEY = 'auth:public-key';
    public const CACHE_TTL_SECONDS = 86400; // 24h — well under Auth Service's token TTL cadence
    private const LOCK_TIMEOUT_SECONDS = 10;
    private const LOCK_WAIT_SECONDS = 5;

    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return $this->error(errorCode: 'unauthenticated', message: 'Missing bearer token', status: 401);
        }

        $publicKey = $this->getPublicKey();

        if (!$publicKey) {
            return $this->error(errorCode: 'SERVER_ERROR', message: 'Auth public key not available', status: 500);
        }

        try {
            $decoded = $this->decode($token, $publicKey);
        } catch (ExpiredException $e) {
            return $this->error(errorCode: 'TOKEN_EXPIRED', message: 'Token expired', status: 401);
        } catch (SignatureInvalidException $e) {
            // Key may have rotated on the Auth Service side — bust cache and retry once
            Cache::forget(self::CACHE_KEY);
            $freshKey = $this->getPublicKey();

            if (!$freshKey) {
                return $this->error(errorCode: 'SERVER_ERROR', message: 'Auth public key not available', status: 500);
            }

            try {
                $decoded = $this->decode($token, $freshKey);
            } catch (\Throwable $e) {
                return $this->error(errorCode: 'TOKEN_INVALID', message: 'Invalid token: ' . $e->getMessage(), status: 401);
            }
        } catch (\Throwable $e) {
            return $this->error(errorCode: 'TOKEN_INVALID', message: 'Invalid token: ' . $e->getMessage(), status: 401);
        }

        $tokenScopes = (array) ($decoded->scopes ?? $decoded->scp ?? []);
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes, true)) {
                return $this->error(
                    errorCode: 'insufficient_scope',
                    message: "Missing required scope: {$scope}",
                    status: 403,
                );
            }
        }

        $request->attributes->set('jwt_claims', (array) $decoded);
        $request->attributes->set('user_uuid', $decoded->uuid ?? $decoded->sub ?? null);
        $request->attributes->set('user_role', $decoded->role ?? null);

        return $next($request);
    }

    private function decode(string $token, string $publicKey): object
    {
        return JWT::decode($token, new Key($publicKey, 'RS256'));
    }

    /**
     * Returns the cached public key, or fetches it from Auth Service on a
     * cache miss. A Redis lock prevents a thundering herd of HTTP calls
     * to Auth Service when the cache is cold.
     */
    private function getPublicKey(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached) {
            return $cached;
        }

        $lock = Cache::lock(self::CACHE_KEY . ':lock', self::LOCK_TIMEOUT_SECONDS);

        return $lock->block(self::LOCK_WAIT_SECONDS, function () {
            // Re-check — another request may have populated it while we waited for the lock
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached) {
                return $cached;
            }

            $key = $this->fetchPublicKeyFromAuthService();

            if ($key) {
                Log::info('Fetch from db');
                Cache::put(self::CACHE_KEY, $key, self::CACHE_TTL_SECONDS);
            }

            return $key;
        });
    }

    private function fetchPublicKeyFromAuthService(): ?string
    {
        try {
            $response = Http::timeout(5)
                ->retry(2, 200)
                ->get(config('services.auth.url') . '/api/v1/auth/public-key');

            if (!$response->successful()) {
                Log::error('ValidateJwt: failed to fetch public key from auth-service', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            // Adjust the key name below to match whatever field auth-service's
            // TokenController@publicKey actually returns (e.g. 'public_key').
            return $response->json('data.public_key') ?? $response->json('public_key');
        } catch (ConnectionException $e) {
            Log::error('ValidateJwt: could not reach auth-service to fetch public key: ' . $e->getMessage());
            return null;
        }
    }
}
