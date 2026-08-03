<?php

namespace Ecomstarter\Core\Exceptions;

use Ecomstarter\Core\Response\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;
use Ecomstarter\Core\Support\TraceId;

abstract class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    // ── Main entry point ─────────────────────────────────────────────────────

    public function render($request, Throwable $e): mixed
    {
        if ($request->is('api/*') || $request->is('oauth/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    // ── Core handler ─────────────────────────────────────────────────────────

    protected function handleApiException($request, Throwable $e): JsonResponse
    {
        $e = $this->prepareException($e);

        // Always first — ensures app('trace-id') is set before
        // anything else runs, including logException and ApiResponse
        $this->resolveTraceId();

        $this->logException($e);

        // Allow subclasses (e.g. auth-service Handler) to intercept
        // their own service-specific exceptions first
        $custom = $this->handleServiceException($e);
        if ($custom !== null) {
            return $custom;
        }

        return match (true) {
            $e instanceof AuthenticationException
                => $this->error('unauthenticated', 'Unauthenticated.', 401),

            $e instanceof AuthorizationException
                => $this->error('unauthorized', 'You do not have permission.', 403),

            $e instanceof ValidationException
                => $this->validationError($e),

            $e instanceof NotFoundHttpException
                => $this->error('not_found', 'Resource not found.', 404),

            $e instanceof MethodNotAllowedHttpException
                => $this->error('method_not_allowed', 'Method not allowed.', 405),

            $e instanceof TooManyRequestsHttpException
                => $this->error('too_many_requests', 'Too many requests. Slow down.', 429),

            $e instanceof HttpException
                => $this->error('http_error', $e->getMessage() ?: 'HTTP error.', $e->getStatusCode()),

            default
            => $this->serverError($e),
        };
    }

    // ── Hook for subclasses ──────────────────────────────────────────────────

    /**
     * Override in each service's Handler to handle service-specific exceptions.
     * Return null to fall through to the base handler.
     */
    protected function handleServiceException(Throwable $e): ?JsonResponse
    {
        return null;
    }

    // ── Response builders ────────────────────────────────────────────────────

    protected function error(
        string  $errorCode,
        string  $message,
        int     $status,
        mixed   $details = null,
    ): JsonResponse {
        return ApiResponse::error($errorCode, $message, $status, $details);
    }

    protected function validationError(ValidationException $e): JsonResponse
    {
        return ApiResponse::validationError($e->errors());
    }

    protected function serverError(Throwable $e): JsonResponse
    {
        // NEVER leak internal exception messages in production
        $message = app()->isProduction()
            ? 'An unexpected error occurred.'
            : $e->getMessage();

        return ApiResponse::serverError($message);
    }

    // ── Logging — right level per exception type ─────────────────────────────

    protected function logException(Throwable $e): void
    {
        $context = [
            'trace_id'  => $this->resolveTraceId(),
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ];

        match (true) {
            // Expected flow — debug only
            $e instanceof AuthenticationException,
            $e instanceof AuthorizationException,
            $e instanceof ValidationException,
            $e instanceof NotFoundHttpException,
            $e instanceof MethodNotAllowedHttpException
                => Log::debug($e->getMessage(), $context),

            // Client errors — info
            $e instanceof TooManyRequestsHttpException
                => Log::info($e->getMessage(), $context),

            // Real problems — error
            default => Log::error($e->getMessage(), $context),
        };
    }

    // ── Trace ID resolution ──────────────────────────────────────────────────

    protected function resolveTraceId(): string
    {
        return TraceId::ensure();
    }

    // ── Standard register ────────────────────────────────────────────────────

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
