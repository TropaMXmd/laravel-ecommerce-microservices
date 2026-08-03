<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Token;
use Laravel\Passport\Client;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\ServerRequest;

class TokenService
{
    public function issueTokenForUser(User $user, string $password): array
    {
        // Load permissions first — passportScopes() calls getAllPermissions()
        // which needs the Spatie relationship loaded
        $user->loadMissing('permissions', 'roles.permissions');

        $response = Http::asForm()->post(
            'http://auth-nginx/oauth/token',
            [
                'grant_type'    => 'password',
                'client_id'     => config('passport.password_client_id'),
                'client_secret' => config('passport.password_client_secret'),
                'username'      => $user->email,
                'password'      => $password,
                'scope'         =>  implode(' ', $user->passportScopes())
            ]
        );

        if (! $response->ok()) {
            throw new \RuntimeException('Token issuance failed: ' . $response->body());
        }

        return $response->json();
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->token()->revoke();
    }

    public function revokeAllTokensForUser(User $user): void
    {
        $user->tokens()->each(function (Token $token) {
            $token->revoke();
            $token->refreshToken?->revoke();
        });
    }

    public function getPublicKey(): string
    {
        $path = storage_path('oauth-public.key');

        if (! file_exists($path)) {
            throw new \RuntimeException(
                'Public key not found. Run: php artisan passport:keys'
            );
        }

        return file_get_contents($path);
    }

    public function introspect(string $tokenId): array
    {
        $token = Token::find($tokenId);

        if (! $token || $token->revoked || Carbon::now()->gt($token->expires_at)) {
            return ['active' => false];
        }

        return [
            'active'     => true,
            'user_id'    => $token->user_id,
            'scopes'     => $token->scopes,
            'expires_at' => $token->expires_at->toIso8601String(),
        ];
    }
}
