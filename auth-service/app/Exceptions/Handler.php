<?php

namespace App\Exceptions;


use App\Exceptions\AccountInactiveException;
use App\Exceptions\InvalidCredentialsException;
use Ecomstarter\Core\Exceptions\Handler as BaseHandler;
use Illuminate\Http\JsonResponse;
use Laravel\Passport\Exceptions\OAuthServerException;
use Throwable;

class Handler extends BaseHandler
{
    /**
     * Handle auth-service specific exceptions.
     * Base handler calls this before its own match() block.
     */
    protected function handleServiceException(Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof OAuthServerException
                => $this->error('invalid_credentials', 'The credentials were incorrect.', 401),

            $e instanceof InvalidCredentialsException
                => $this->error('invalid_credentials', $e->getMessage(), 401),

            $e instanceof AccountInactiveException
                => $this->error('account_inactive', $e->getMessage(), 403),

            default => null,    // fall through to base handler
        };
    }
}