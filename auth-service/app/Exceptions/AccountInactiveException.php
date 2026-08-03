<?php
// auth-service/app/Exceptions/AccountInactiveException.php

namespace App\Exceptions;

use RuntimeException;

class AccountInactiveException extends RuntimeException
{
    public function __construct(
        string $message = 'Your account has been deactivated.'
    ) {
        parent::__construct($message);
    }
}