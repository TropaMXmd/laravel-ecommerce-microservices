<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Ecomstarter\Core\Repository\BaseRepository;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function findActiveByEmail(string $email): ?User
    {
        return $this->model
            ->where('email', $email)
            ->where('is_active', true)
            ->first();
    }

    public function updateLastLogin(User $user): void
    {
        $user->update(['last_login_at' => now()]);
    }

    public function deactivate(User $user): void
    {
        $user->update(['is_active' => false]);
    }

    protected function applyFilters($query, array $filters)
    {
        return $query
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($q) use ($filters) {
                    $q->where('name', 'LIKE', "%{$filters['search']}%")
                      ->orWhere('email', 'LIKE', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['role']),
                fn ($q) => $q->where('role', $filters['role'])
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', $filters['is_active'])
            );
    }

    protected function sortableColumns(): array
    {
        return ['created_at', 'updated_at', 'name', 'email', 'last_login_at'];
    }
}
