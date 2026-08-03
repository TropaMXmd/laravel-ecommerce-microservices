<?php

namespace Ecomstarter\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Ecomstarter\Core\Support\TraceId;

class TraceIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = TraceId::fromRequest($request);  // validate + generate in one call

        TraceId::set($traceId);                     // store in container
        Log::withContext([TraceId::LOG_KEY => $traceId]);

        $response = $next($request);
        $response->headers->set(TraceId::HEADER, $traceId);

        return $response;
    }
}
