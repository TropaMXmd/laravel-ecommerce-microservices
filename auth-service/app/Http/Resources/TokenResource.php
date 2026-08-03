<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token'  => $this->resource['access_token'],
            'refresh_token' => $this->resource['refresh_token'] ?? null,
            'token_type'    => $this->resource['token_type']    ?? 'Bearer',
            'expires_in'    => $this->resource['expires_in'],
        ];
    }
}
