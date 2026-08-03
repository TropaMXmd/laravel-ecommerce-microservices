<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'subtotal',
        'tax',
        'shipping_fee',
        'total',
        'currency',
        'shipping_name',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'cancellation_reason',
        'cancelled_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'tax'          => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total'        => 'decimal:2',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function outboxEvents(): HasMany
    {
        return $this->hasMany(OrderOutbox::class, 'aggregate_id');
    }

    public function isPending(): bool    
    { 
        return $this->status === 'pending'; 
    }
    
    public function isConfirmed(): bool  
    { 
        return $this->status === 'confirmed'; 
    }
    public function isShipped(): bool   
    { 
        return $this->status === 'shipped'; 
    }
    public function isDelivered(): bool  
    { 
        return $this->status === 'delivered'; 
    }
    public function isCancelled(): bool  
    { 
        return $this->status === 'cancelled'; 
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled']);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}