<?php

namespace Ecomstarter\Core\Models;

use Illuminate\Database\Eloquent\Model;

class HttpLog extends Model
{
    public $timestamps = false;         // table only has created_at, no updated_at
    public $incrementing = false;       // UUID primary key
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'trace_id',
        'direction',
        'service',
        'method',
        'url',
        'request_headers',
        'request_body',
        'response_status',
        'response_body',
        'response_headers',
        'ip_address',
        'duration_ms',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'request_headers'  => 'array',
        'request_body'     => 'array',
        'response_body'    => 'array',
        'response_headers' => 'array',
        'response_status'  => 'integer',
        'duration_ms'      => 'float',
        'created_at'       => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeErrors($query)
    {
        return $query->where('response_status', '>=', 400);
    }

    public function scopeForTrace($query, string $traceId)
    {
        return $query->where('trace_id', $traceId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSlow($query, float $thresholdMs = 1000)
    {
        return $query->where('duration_ms', '>=', $thresholdMs);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isSuccess(): bool
    {
        return $this->response_status < 400;
    }

    public function isError(): bool
    {
        return $this->response_status >= 400;
    }

    public function isServerError(): bool
    {
        return $this->response_status >= 500;
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }
}