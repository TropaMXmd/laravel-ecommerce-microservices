<?php

namespace Ecomstarter\Core\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Ecomstarter\Core\Support\TraceId;

/**
 * ApiResponse — single source of truth for all API response formats.
 *
 * Used by:
 *   - Controllers (via ApiResponseTrait)
 *   - Exception Handler (directly)
 *   - Middleware (directly)
 *
 * Every microservice that installs ecomstarter/core gets
 * identical response envelopes automatically.
 */
class ApiResponse
{
    // ── Success ───────────────────────────────────────────────────────────────

    public static function success(
        mixed  $data    = null,
        string $message = '',
        int    $status  = 200,
    ): JsonResponse {
        $body = [
            'success'   => true,
            'message'   => $message,
            'data'      => $data,
            'trace_id'  => static::traceId(),
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($body, $status);
    }

    // ── Created ───────────────────────────────────────────────────────────────

    public static function created(
        mixed  $data    = null,
        string $message = 'Resource created successfully.',
    ): JsonResponse {
        return static::success($data, $message, 201);
    }

    // ── Accepted (async operations like order placement) ──────────────────────

    public static function accepted(
        mixed  $data    = null,
        string $message = 'Request accepted and is being processed.',
    ): JsonResponse {
        return static::success($data, $message, 202);
    }

    // ── No content ────────────────────────────────────────────────────────────

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    // ── Paginated ─────────────────────────────────────────────────────────────

    public static function paginated(
        LengthAwarePaginator $paginator,
        string               $message = '',
    ): JsonResponse {
        $body = [
            'success'   => true,
            'message'   => $message,
            'data'      => $paginator->items(),
            'meta'      => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
            'trace_id'  => static::traceId(),
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($body, 200);
    }

    // ── Error ─────────────────────────────────────────────────────────────────

    public static function error(
        string $errorCode,
        string $message,
        int    $status  = 400,
        mixed  $details = null,
    ): JsonResponse {
        $body = [
            'success'    => false,
            'error_code' => $errorCode,
            'message'    => $message,
            'details'    => $details,
            'trace_id'   => static::traceId(),
            'timestamp'  => now()->toIso8601String(),
        ];

        return response()->json($body, $status);
    }

    // ── Common error shortcuts ────────────────────────────────────────────────

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return static::error('not_found', $message, 404);
    }

    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return static::error('unauthenticated', $message, 401);
    }

    public static function forbidden(string $message = 'You do not have permission.'): JsonResponse
    {
        return static::error('unauthorized', $message, 403);
    }

    public static function validationError(array $errors): JsonResponse
    {
        return static::error(
            errorCode: 'validation_error',
            message:   'The given data was invalid.',
            status:    422,
            details:   $errors,
        );
    }

    public static function serverError(string $message = 'An unexpected error occurred.'): JsonResponse
    {
        return static::error('server_error', $message, 500);
    }

    // ── Trace ID ──────────────────────────────────────────────────────────────

    protected static function traceId(): string
    {
        return TraceId::get();
    }
}
