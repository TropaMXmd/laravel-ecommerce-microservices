<?php

namespace App\DTOs;

use Ecomstarter\Core\DTO\BaseDTO;
use Illuminate\Http\Request;

class CreateProductDTO extends BaseDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly string $currency,
        public readonly ?array $attributes,
        public readonly int $initialQuantity,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            sku:            $request->validated('sku'),
            name:           $request->validated('name'),
            description:    $request->validated('description'),
            price:          (float) $request->validated('price'),
            currency:       $request->validated('currency') ?? 'BDT',
            attributes:     $request->validated('attributes'),
            initialQuantity: (int) ($request->validated('initial_quantity') ?? 0),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            sku:            $data['sku'],
            name:           $data['name'],
            description:    $data['description'] ?? null,
            price:          (float) $data['price'],
            currency:       $data['currency'] ?? 'BDT',
            attributes:     $data['attributes'] ?? null,
            initialQuantity: (int) ($data['initial_quantity'] ?? 0),
        );
    }
}
