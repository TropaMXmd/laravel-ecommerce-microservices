<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'attributes' => $this->attributes,
            'stock' => [
                'quantity' => $this->stock?->quantity ?? 0,
                'reserved' => $this->stock?->reserved_quantity ?? 0,
                'available' => $this->stock?->available ?? 0,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
