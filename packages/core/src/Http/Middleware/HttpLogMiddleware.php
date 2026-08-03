<?php

namespace Ecomstarter\Core\Http\Middleware;

use Closure;
use Ecomstarter\Core\Support\TraceId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class HttpLogMiddleware
{
    private const MASKED_HEADERS = [
        'authorization', 'cookie', 'x-api-key',
    ];

    private const MASKED_BODY_FIELDS = [
        'password', 'password_confirmation',
        'current_password', 'token', 'secret',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip if logging disabled
        if (! config('http_log.enabled', true)) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Decide whether to log based on config level
        if ($this->shouldLog($response->getStatusCode())) {
            $this->writeLog($request, $response, $duration, 'incoming');
        }

        return $response;
    }

    private function shouldLog(int $statusCode): bool
    {
        return match (config('http_log.level', 'all')) {
            'errors_only' => $statusCode >= 400,
            'none'        => false,
            default       => true,   // 'all'
        };
    }

    private function writeLog(
        Request  $request,
        Response $response,
        float    $duration,
        string   $direction,
    ): void {
        try {
            DB::table('http_logs')->insert([
                'id'               => (string) Str::uuid(),
                'trace_id'         => TraceId::get(),
                'direction'        => $direction,
                'service'          => config('app.name'),
                'method'           => $request->method(),
                'url'              => $request->fullUrl(),
                'request_headers'  => json_encode($this->sanitiseHeaders(
                    $request->headers->all()
                )),
                'request_body'     => json_encode($this->sanitiseBody(
                    $request->all()
                )),
                'response_status'  => $response->getStatusCode(),
                'response_body'    => json_encode(
                    json_decode($response->getContent(), true)
                ),
                'response_headers' => json_encode(
                    $response->headers->all()
                ),
                'ip_address'       => $request->ip(),
                'duration_ms'      => $duration,
                'user_id'          => $request->user()?->id,
                'created_at'       => now(),
            ]);
        } catch (\Throwable) {
            // Logging must never break the request
            // Fail silently — log to file as fallback
            \Illuminate\Support\Facades\Log::warning(
                'HttpLogMiddleware: failed to write http log',
                ['trace_id' => TraceId::get()]
            );
        }
    }

    private function sanitiseHeaders(array $headers): array
    {
        foreach (self::MASKED_HEADERS as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[FILTERED]'];
            }
        }

        return $headers;
    }

    private function sanitiseBody(array $body): array
    {
        foreach (self::MASKED_BODY_FIELDS as $field) {
            if (isset($body[$field])) {
                $body[$field] = '[FILTERED]';
            }
        }

        return $body;
    }
}