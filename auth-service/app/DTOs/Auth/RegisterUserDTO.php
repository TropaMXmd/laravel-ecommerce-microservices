<?php

namespace App\DTOs\Auth;

use Ecomstarter\Core\DTO\BaseDTO;
use Illuminate\Http\Request;

class RegisterUserDTO extends BaseDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $email,
        public readonly string  $password,
        public readonly ?string $phone = null,
        public readonly string  $role  = 'customer',
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            name:     $request->validated('name'),
            email:    $request->validated('email'),
            password: $request->validated('password'),
            phone:    $request->validated('phone'),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            name:     $data['name'],
            email:    $data['email'],
            password: $data['password'],
            phone:    $data['phone'] ?? null,
            role:     $data['role']  ?? 'customer',
        );
    }
}
