<?php

namespace App\DTOs;

use Ecomstarter\Core\DTO\BaseDTO;
use Illuminate\Http\Request;

class AdjustStockDTO extends BaseDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly int $delta,
        public readonly ?string $reason,
    ) {
    }

    public static function fromRequest(Request $request): static
    {
        return new static(
            sku:    $request->validated('sku'),
            delta:  (int) $request->validated('delta'),
            reason: $request->validated('reason'),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            sku:    $data['sku'],
            delta:  (int) $data['delta'],
            reason: $data['reason'] ?? null,
        );
    }
}
