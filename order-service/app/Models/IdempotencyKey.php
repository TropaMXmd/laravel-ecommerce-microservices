<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'request_method',
        'request_path',
        'response_status',
        'response_body',
        'response_headers',
        'user_id',
        'expires_at',
    ];

    protected $casts = [
        'response_body'    => 'array',
        'response_headers' => 'array',
        'expires_at'       => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}