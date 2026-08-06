<?php

namespace Ecomstarter\Core\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Validates RS256-signed JWTs issued by auth-service, entirely locally —
 * no per-request call to auth-service. Shared across every token-consuming
 * service (inventory, order, notification). auth-service itself does not
 * use this middleware — it issues tokens, it doesn't validate its own.
 *
 * Public key is fetched once from GET /api/v1/auth/public-key, cached in
 * Redis for 24h. Each service's own APP_NAME-based key prefixing keeps the
 * cached copies isolated per service even though the literal cache key
 * string is identical — that's intentional, not a collision risk.
 *
 * On a signature-verification failure we assume the key rotated: bust the
 * cache, refetch, and retry exactly once before failing.
 *
 * Consuming service must define config('services.auth.base_url').
 *
 * Usage in routes:
 *   Route::middleware('validate.jwt')->group(...)                 // auth only
 *   Route::middleware('validate.jwt:orders.create')->post(...)     // auth + scope check
 */
class ValidateJwt
{
    public const CACHE_KEY = 'auth-service:public-key';
    public const CACHE_TTL_SECONDS = 60 * 60 * 24; // 24h

    protected bool $lastFailureWasSignature = false;

    public function handle(Request $request, Closure $next, ?string $requiredPermission = null): Response
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return $this->unauthorized('Missing bearer token.');
        }

        $claims = $this->decodeWithRetry($token);

        if ($claims === null) {
            return $this->unauthorized('Invalid or expired token.');
        }

        if (($claims['is_active'] ?? true) === false) {
            return $this->unauthorized('User account is inactive.');
        }

        if ($requiredPermission !== null && !$this->hasScope($claims, $requiredPermission)) {
            return response()->json([
                'success' => false,
                'message' => "Missing required permission: {$requiredPermission}",
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('jwt_claims', $claims);
        $request->attributes->set('auth_user_uuid', $claims['sub'] ?? $claims['uuid'] ?? null);

        return $next($request);
    }

    protected function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

    protected function decodeWithRetry(string $token): ?array
    {
        $publicKey = $this->getPublicKey();

        if ($publicKey === null) {
            Log::error(config('app.name') . '.jwt: could not obtain auth-service public key.');
            return null;
        }

        $claims = $this->tryDecode($token, $publicKey);

        if ($claims !== null) {
            return $claims;
        }

        if (!$this->lastFailureWasSignature) {
            return null;
        }

        Log::warning(config('app.name') . '.jwt: signature verification failed, assuming key rotation, refetching.');
        Cache::forget(self::CACHE_KEY);
        $freshKey = $this->getPublicKey();

        if ($freshKey === null) {
            return null;
        }

        return $this->tryDecode($token, $freshKey);
    }

    protected function tryDecode(string $token, string $publicKeyPem): ?array
    {
        $this->lastFailureWasSignature = false;

        try {
            $decoded = JWT::decode($token, new Key($publicKeyPem, 'RS256'));
            return (array) $decoded;
        } catch (SignatureInvalidException $e) {
            $this->lastFailureWasSignature = true;
            return null;
        } catch (ExpiredException $e) {
            Log::info(config('app.name') . '.jwt: token expired.');
            return null;
        } catch (UnexpectedValueException $e) {
            Log::warning(config('app.name') . '.jwt: token decode error: ' . $e->getMessage());
            return null;
        }
    }

    protected function getPublicKey(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        
        if ($cached) {
            return $cached;
        }

        return $this->fetchAndCachePublicKey();
    }

    protected function fetchAndCachePublicKey(): ?string
    {
        $key = $this->fetchPublicKeyFromAuthService();
        
        if ($key !== null) {
            Cache::put(self::CACHE_KEY, $key, self::CACHE_TTL_SECONDS);
        }

        return $key;
    }

    protected function fetchPublicKeyFromAuthService(): ?string
    {
        try {
            $response = Http::timeout(5)
                ->retry(2, 200)
                ->get(rtrim(config('core.auth.base_url'), '/') . '/api/v1/auth/public-key');

            if (!$response->successful()) {
                Log::error(config('app.name') . '.jwt: auth-service public-key fetch failed', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $key = $response->json('public_key') ?? $response->json('data.public_key');

            return $key ?: null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Auth-service unreachable — nothing to cache. The caller (a
            // request mid-flight) will fail this one lookup; the scheduled
            // RefreshAuthPublicKeyJob and the next request will both retry
            // independently, no special handling needed here.
            Log::warning(config('app.name') . '.jwt: could not reach auth-service: ' . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            Log::error(config('app.name') . '.jwt: exception fetching public key: ' . $e->getMessage());
            return null;
        }
    }

    protected function hasScope(array $claims, string $permission): bool
    {
        $scopes = $claims['scopes'] ?? [];

        return in_array($permission, (array) $scopes, true);
    }

    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}