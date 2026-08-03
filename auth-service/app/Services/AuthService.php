<?php

namespace App\Services;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Exceptions\AccountInactiveException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\TokenService;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenService            $tokenService,
    ) {
    }

    public function register(RegisterUserDTO $dto): array
    {
        $user = $this->userRepository->create([
            'name'      => $dto->name,
            'email'     => $dto->email,
            'password'  => Hash::make($dto->password),
            'phone'     => $dto->phone,
            'is_active' => true,
        ]);

        // Assign Spatie role
        $user->assignRole('customer');

        // Refresh to load the role + permissions relationship
        $user->refresh()->load('roles.permissions', 'permissions');

        $tokens = $this->tokenService->issueTokenForUser($user, $dto->password);

        return compact('user', 'tokens');
    }

    public function login(LoginDTO $dto): array
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        if (! $user->isActive()) {
            throw new AccountInactiveException();
        }

        $this->userRepository->updateLastLogin($user);

        $tokens = $this->tokenService->issueTokenForUser($user, $dto->password);

        return compact('user', 'tokens');
    }

    public function logout(User $user): void
    {
        $this->tokenService->revokeAllTokensForUser($user);
    }
}
