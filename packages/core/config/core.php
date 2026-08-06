<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auth Service
    |--------------------------------------------------------------------------
    |
    | Base URL for auth-service, used by:
    |   - Ecomstarter\Core\Http\Middleware\ValidateJwt (fetches the RS256
    |     public key, and refetches reactively on signature failure)
    |   - Ecomstarter\Core\Jobs\RefreshAuthPublicKeyJob (proactive hourly
    |     refresh)
    |
    | Every consuming service needs AUTH_SERVICE_URL set in its own .env —
    | this file just gives that setting one canonical name and default
    | across the whole platform, instead of each service inventing its own
    | config('services.auth.*') key independently.
    |
    | Use the Docker Compose service name here, not localhost — this URL is
    | called from inside other containers on the compose network, not from
    | the host.
    |
    */
    'auth' => [
        'base_url' => env('AUTH_SERVICE_URL', 'http://auth-nginx'),
    ],
];