<?php

namespace App\DTOs\User;

readonly class UserFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $role = null,
        public ?bool $isActive = null,
        public ?string $sortBy = 'created_at',
        public ?string $sortDirection = 'desc',
        public int $page = 1,
        public int $pageSize = 15,
    ) {}
}