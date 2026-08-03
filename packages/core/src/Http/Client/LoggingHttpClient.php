<?php

namespace Ecomstarter\Core\Http\Client;

use Ecomstarter\Core\Support\TraceId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoggingHttpClient
{
    public static function post(string $url, array $data = []): \Illuminate\Http\Client\Response
    {
        $startTime = microtime(true);

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            TraceId::HEADER => TraceId::get(),   // always forward trace ID
        ])->post($url, $data);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if (config('http_log.log_outgoing', true)) {
            self::logOutgoing('POST', $url, $data, $response, $duration);
        }

        return $response;
    }

    private static function logOutgoing(
        string $method,
        string $url,
        array  $requestData,
        $response,
        float  $duration,
    ): void {
        try {
            DB::table('http_logs')->insert([
                'id'              => (string) Str::uuid(),
                'trace_id'        => TraceId::get(),
                'direction'       => 'outgoing',
                'service'         => config('app.name'),
                'method'          => $method,
                'url'             => $url,
                'request_body'    => json_encode($requestData),
                'response_status' => $response->status(),
                'response_body'   => $response->body(),
                'duration_ms'     => $duration,
                'created_at'      => now(),
            ]);
        } catch (\Throwable) {
            Log::warning('LoggingHttpClient: failed to write outgoing log');
        }
    }
}