<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\IdempotencyKey;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckIdempotency
{
    private const IDEMPOTENCY_KEY_HEADER = 'Idempotency-Key';
    private const IDEMPOTENCY_KEY_EXPIRATION = 60 * 60 * 24; // 24 hours
    private const IDEMPOTENCY_KEY_PREFIX = 'idem-key:';

    public function handle(Request $request, Closure $next): Response
    {
        if(in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) 
            return $next($request);

        $idempotencyKey = $request->header(self::IDEMPOTENCY_KEY_HEADER);
        if (!$idempotencyKey) {
            return response()->json(['error' => 'Idempotency key is required'], 400);
        }
    
        if(!$this->isValidIdempotencyKey($idempotencyKey)) {
            return response()->json(['error' => 'Invalid idempotency key format'], 422);
        }

        // Check if the idempotency key already exists in Redis
        $redisKey = self::IDEMPOTENCY_KEY_PREFIX . $idempotencyKey;
        if (cache()->has($redisKey)) {
            return response()->json(['error' => 'Duplicate request'], 409);
        }

        $record = IdempotencyKey::where('key', $redisKey)
            ->where('request_path', $request->path())
            ->where('expires_at', '>', now())
            ->first();

        if ($record) {
            // Warm Redis cache from DB so next request is fast again
            cache()->put($redisKey, $record->only([
                'response_status',
                'response_body',
                'response_headers',
            ]), now()->addHours(self::IDEMPOTENCY_KEY_EXPIRATION / 3600));

            return $this->replayResponse([
                'response_status'  => $record->response_status,
                'response_body'    => $record->response_body,
                'response_headers' => $record->response_headers ?? [],
            ], source: 'db');
        }

        $response = $next($request);
        if ($response->getStatusCode() < 500) {
            $this->storeResponse($redisKey, $request, $response, $request->user()?->id);
        }

        return $response;
    }

    private function isValidIdempotencyKey(string $key): bool
    {
        // Check if the key is a valid UUID
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $key) === 1;
    }

    private function replayResponse(array $stored, string $source): SymfonyResponse
    {
        $response = response()->json(
            $stored['response_body'],
            $stored['response_status']
        );

        // Signal to client that this is a replayed response
        $response->headers->set('Idempotency-Replayed', 'true');
        $response->headers->set('Idempotency-Source', $source);

        return $response;
    }

    private function storeResponse(
        string          $redisKey,
        Request         $request,
        SymfonyResponse $response,
        ?int            $userId,
    ): void {
        $data = [
            'response_status'  => $response->getStatusCode(),
            'response_body'    => json_decode($response->getContent(), true) ?? [],
            'response_headers' => $this->safeHeaders($response),
        ];

        // Write to Redis first (fast)
        cache()->put($redisKey, $data, now()->addHours(self::IDEMPOTENCY_KEY_EXPIRATION));

        // Write to DB for durability
        try {
            IdempotencyKey::create([
                'key'              => $redisKey,
                'request_method'   => $request->method(),
                'request_path'     => $request->path(),
                'response_status'  => $data['response_status'],
                'response_body'    => $data['response_body'],
                'response_headers' => $data['response_headers'],
                'user_id'          => $userId,
                'expires_at'       => now()->addHours(self::IDEMPOTENCY_KEY_EXPIRATION),
            ]);
        } catch (\Exception $e) {
            Log::error('Idempotency DB write failed', [
                'key'   => $redisKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function safeHeaders(SymfonyResponse $response): array
    {
        // Only preserve safe, relevant headers — not cookies or auth tokens
        $safe = ['Content-Type', 'X-Request-Id'];

        $headers = [];
        foreach ($safe as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }
}
