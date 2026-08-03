<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;
    public function findByUuid(string $uuid): ?Order;
    public function create(array $data): Order;
}