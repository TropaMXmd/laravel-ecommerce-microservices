<?php

namespace Ecomstarter\Core\Repository;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    // ── Read ─────────────────────────────────────────────────────────────────

    public function findById(int $id): ?Model
    {
        return $this->model
            ->with($this->relations())
            ->find($id);
    }

    public function findByUuid(string $uuid): ?Model
    {
        return $this->model
            ->with($this->relations())
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByUuidOrFail(string $uuid): Model
    {
        return $this->model
            ->with($this->relations())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Paginate with plain array filters — no Request object.
     * Each service repository overrides applyFilters() with its own logic.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with($this->relations());
        $query = $this->applyFilters($query, $filters);
        $query = $this->applySort($query, $filters);

        return $query->paginate($perPage);
    }

    public function all(array $filters = []): Collection
    {
        $query = $this->model->with($this->relations());
        $query = $this->applyFilters($query, $filters);

        return $query->get();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function create(array $data): Model
    {
        return $this->model->create(
            $this->beforeCreate($data)
        );
    }

    public function update(Model $model, array $data): Model
    {
        // Takes the model instance, not an ID
        // Caller is responsible for finding the model first
        $model->update($this->beforeUpdate($data));

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    // ── Extension hooks ───────────────────────────────────────────────────────

    protected function relations(): array
    {
        return [];
    }

    protected function applyFilters($query, array $filters)
    {
        return $query;
    }

    protected function applySort($query, array $filters)
    {
        $sort      = $filters['sort']      ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        // Whitelist allowed sort columns — prevent SQL injection
        $allowed = $this->sortableColumns();

        if (in_array($sort, $allowed, true)) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        }

        return $query;
    }

    protected function sortableColumns(): array
    {
        return ['created_at', 'updated_at'];
    }

    protected function beforeCreate(array $data): array
    {
        return $data;
    }

    protected function beforeUpdate(array $data): array
    {
        return $data;
    }
}