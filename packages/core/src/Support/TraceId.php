<?php

namespace Ecomstarter\Core\Support;

use Illuminate\Support\Str;

/**
 * TraceId
 *
 * Single source of truth for trace ID generation and validation.
 * Used by TraceIdMiddleware, Handler, ApiResponse, and any HTTP client
 * that forwards trace IDs between services.
 *
 * Format: standard UUID v4
 * Example: 550e8400-e29b-41d4-a716-446655440000
 */
class TraceId
{
    public const HEADER        = 'X-Trace-ID';
    public const CONTAINER_KEY = 'trace-id';
    public const LOG_KEY       = 'trace_id';    // underscored for log readability
    public const MAX_LENGTH    = 36;            // UUID v4 is always exactly 36 chars

    // ── Generation ────────────────────────────────────────────────────────────

    public static function generate(): string
    {
        return (string) Str::uuid();
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public static function isValid(string $value): bool
    {
        return strlen($value) === self::MAX_LENGTH
            && preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $value
            );
    }

    // ── Container ─────────────────────────────────────────────────────────────

    /**
     * Store a trace ID in the service container.
     * Called by TraceIdMiddleware and Handler.
     */
    public static function set(string $traceId): void
    {
        app()->instance(self::CONTAINER_KEY, $traceId);
    }

    /**
     * Read the current trace ID from the container.
     * Returns empty string if not set — never generates here.
     */
    public static function get(): string
    {
        return app()->has(self::CONTAINER_KEY)
            ? app(self::CONTAINER_KEY)
            : '';
    }

    /**
     * Check if a trace ID is currently bound in the container.
     */
    public static function exists(): bool
    {
        return app()->has(self::CONTAINER_KEY);
    }

    // ── Resolution ────────────────────────────────────────────────────────────

    /**
     * Resolve from an incoming request header.
     * Accepts the header value if valid, generates a fresh one if not.
     * Used by TraceIdMiddleware.
     */
    public static function fromRequest(\Illuminate\Http\Request $request): string
    {
        $incoming = $request->header(self::HEADER);

        if ($incoming && static::isValid($incoming)) {
            return $incoming;
        }

        return static::generate();
    }

    /**
     * Ensure a trace ID exists in the container.
     * If not, generates and stores one.
     * Used by Handler when middleware may not have run.
     * Returns the trace ID — same one whether existing or newly generated.
     */
    public static function ensure(): string
    {
        if (! static::exists()) {
            static::set(static::generate());
        }

        return static::get();
    }
}