<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Ecomstarter\Core\Response\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            RegisterUserDTO::fromRequest($request)
        );

        return $this->created(
            data: [
                'user'   => new UserResource($result['user']),
                'tokens' => new TokenResource($result['tokens']),
            ],
            message: 'Registration successful.',
        );
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            LoginDTO::fromRequest($request)
        );

        return $this->success(
            data: [
                'user'   => new UserResource($result['user']),
                'tokens' => new TokenResource($result['tokens']),
            ],
            message: 'Login successful.',
        );
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(message: 'Logged out successfully.');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            data:    new UserResource($request->user()),
            message: 'User profile retrieved.',
        );
    }

    /**
     * PUT /api/v1/auth/me
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(array_filter([
            'name'  => $request->validated('name'),
            'phone' => $request->validated('phone'),
        ]));

        if ($request->filled('password')) {
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make(
                    $request->validated('password')
                ),
            ]);
        }

        return $this->success(
            data:    new UserResource($user->refresh()),
            message: 'Profile updated successfully.',
        );
    }
}
