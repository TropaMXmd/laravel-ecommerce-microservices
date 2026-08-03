<?php
// auth-service/app/Exceptions/InvalidCredentialsException.php

namespace App\Exceptions;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public function __construct(
        string $message = 'Invalid email or password.'
    ) {
        parent::__construct($message);
    }
}