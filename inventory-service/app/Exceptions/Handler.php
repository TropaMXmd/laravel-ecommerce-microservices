<?php

namespace App\Exceptions;

use Ecomstarter\Core\Exceptions\Handler as CoreHandler;
use Ecomstarter\Core\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class Handler extends CoreHandler
{
    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {

        return match (true) {
            $e instanceof InsufficientStockException
                => $this->error('insufficient_stock', $e->getMessage(), 409),

            $e instanceof ProductNotFoundException
                => $this->error('product_not_found', $e->getMessage(), 404),

            default => null,    // fall through to base handler
        };
    }
}
