<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Ecomstarter\Core\Repository\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    /**
     * array filters, no Request object (per core contract):
     * ['user_id' => string, 'status' => string]
     *
     * user_id is always required in practice — callers should scope every
     * list query to the requesting user unless this is an admin-only route.
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with('items');

        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->withStatus($filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Order
    {
        return $this->model->with('items')->where('uuid', $uuid)->first();
    }

    public function create(array $data): Order
    {
        return $this->model->create($data);
    }
}