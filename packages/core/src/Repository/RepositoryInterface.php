<?php

namespace Ecomstarter\Core\Repository;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface RepositoryInterface
{
    public function findById(int $id): ?Model;
    public function findByUuid(string $uuid): ?Model;
    public function findByUuidOrFail(string $uuid): Model;
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function all(array $filters = []): Collection;
    public function create(array $data): Model;
    public function update(Model $model, array $data): Model;
    public function delete(Model $model): bool;
}