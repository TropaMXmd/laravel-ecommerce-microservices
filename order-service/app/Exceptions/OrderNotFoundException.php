<?php
namespace App\Exceptions;

use RuntimeException;

class OrderNotFoundException extends RuntimeException
{
    public function __construct(string $orderUuid)
    {
        parent::__construct("Order {$orderUuid} not found.");
    }
}