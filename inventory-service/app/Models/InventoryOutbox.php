<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InventoryOutbox extends Model
{
    use HasUuids;

    protected $table = 'inventory_outbox';

    protected $fillable = [
        'aggregate_type', 'aggregate_id', 'event_type', 'payload',
        'routing_key', 'published', 'published_at', 'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
        'published' => 'boolean',
        'published_at' => 'datetime',
    ];
}
