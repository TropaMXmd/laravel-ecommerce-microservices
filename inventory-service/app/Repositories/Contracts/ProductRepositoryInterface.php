<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Ecomstarter\Core\Repository\RepositoryInterface;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findBySku(string $sku): ?Product;
}
