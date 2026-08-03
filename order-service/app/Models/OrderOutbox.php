<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrderOutbox extends Model
{
    protected $table = 'order_outbox';

    protected $fillable = [
        'event_id',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'exchange',
        'routing_key',
        'payload',
        'headers',
        'status',
        'attempts',
        'error',
        'published_at',
        'failed_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'headers'      => 'array',
        'published_at' => 'datetime',
        'failed_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderOutbox $outbox) {
            $outbox->event_id ??= (string) Str::uuid();
        });
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->orderBy('created_at');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ── State transitions ────────────────────────────────────────────────────

    public function markPublished(): void
    {
        $this->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'     => 'failed',
            'error'      => $error,
            'failed_at'  => now(),
            'attempts'   => $this->attempts + 1,
        ]);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'aggregate_id');
    }
}