<?php

namespace Ecomstarter\Core\Response;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Drop this trait into any controller.
 * Keeps controller methods clean — no static class references.
 */
trait ApiResponseTrait
{
    protected function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function created(mixed $data = null, string $message = 'Resource created successfully.'): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function accepted(mixed $data = null, string $message = 'Request accepted.'): JsonResponse
    {
        return ApiResponse::accepted($data, $message);
    }

    protected function noContent(): JsonResponse
    {
        return ApiResponse::noContent();
    }

    protected function paginated(LengthAwarePaginator $paginator, string $message = ''): JsonResponse
    {
        return ApiResponse::paginated($paginator, $message);
    }

    protected function error(string $errorCode, string $message, int $status = 400, mixed $details = null): JsonResponse
    {
        return ApiResponse::error($errorCode, $message, $status, $details);
    }

    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }
}