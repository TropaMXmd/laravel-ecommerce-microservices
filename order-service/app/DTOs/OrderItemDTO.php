<?php

namespace App\DTOs;

use Ecomstarter\Core\DTO\BaseDTO;
use Illuminate\Http\Request;

class OrderItemDTO extends BaseDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly string $productName,
        public readonly float  $unitPrice,
        public readonly int    $quantity,
    ) {}

    public static function fromRequest(Request $request): static
    {
        // Items come as nested array — always use fromArray
        throw new \LogicException('Use fromArray() for order items.');
    }

    public static function fromArray(array $data): static
    {
        return new static(
            sku:         $data['sku'],
            productName: $data['product_name'],
            unitPrice:   (float) $data['unit_price'],
            quantity:    (int)   $data['quantity'],
        );
    }

    public function lineTotal(): float
    {
        return round($this->unitPrice * $this->quantity, 2);
    }
}