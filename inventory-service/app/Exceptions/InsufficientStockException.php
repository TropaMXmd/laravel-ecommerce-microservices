<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly string $sku,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct("Insufficient stock for SKU [{$sku}]: requested {$requested}, available {$available}");
    }
}
