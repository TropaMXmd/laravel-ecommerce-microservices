<?php

namespace App\Exceptions;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderNotFoundException;
use Ecomstarter\Core\Exceptions\Handler as BaseHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class Handler extends BaseHandler
{
    protected function handleServiceException(Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof OrderNotFoundException
                => $this->error('order_not_found', $e->getMessage(), 404),

            $e instanceof InvalidOrderTransitionException
                => $this->error('invalid_transition', $e->getMessage(), 422),

            default => null,
        };
    }
}