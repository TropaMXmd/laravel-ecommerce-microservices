<?php

namespace App\Exceptions;

use Exception;

class ProductNotFoundException extends Exception
{
    public function __construct(string $sku)
    {
        parent::__construct("Product with SKU [{$sku}] not found");
    }
}
