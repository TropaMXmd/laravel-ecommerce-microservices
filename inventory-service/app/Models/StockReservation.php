<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    use HasUuids;

    protected $fillable = ['order_id', 'sku', 'quantity', 'status', 'expires_at'];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
    ];

    public const STATUS_HELD = 'held';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_HELD && $this->expires_at->isPast();
    }
}
