<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Ecomstarter\Core\Repository\RepositoryInterface;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findActiveByEmail(string $email): ?User;
    public function updateLastLogin(User $user): void;
    public function deactivate(User $user): void;
}