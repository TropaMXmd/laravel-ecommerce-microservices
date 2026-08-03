<?php

namespace App\DTOs\Auth;

use Ecomstarter\Core\DTO\BaseDTO;
use Illuminate\Http\Request;

class LoginDTO extends BaseDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            email:    $request->validated('email'),
            password: $request->validated('password'),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            email:    $data['email'],
            password: $data['password'],
        );
    }
}
