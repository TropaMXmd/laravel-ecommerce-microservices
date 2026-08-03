<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\TokenService;
use Ecomstarter\Core\Response\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TokenService $tokenService,
    ) {
    }

    /**
     * GET /api/v1/auth/public-key
     * No auth required — safe to expose to other services.
     */
    public function publicKey(): JsonResponse
    {
        return $this->success([
            'public_key' => $this->tokenService->getPublicKey(),
            'algorithm'  => 'RS256',
        ]);
    }

    /**
     * POST /api/v1/auth/introspect
     * Service-to-service only.
     */
    public function introspect(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();
        $parts = explode('.', $accessToken);

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true
        );

        $tokenId = $payload['jti'];
        return $this->success(
            $this->tokenService->introspect($tokenId)
        );
    }
}
