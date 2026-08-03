<?php

namespace App\DTOs;

use Ecomstarter\Core\DTO\BaseDTO;
use Illuminate\Http\Request;

class PlaceOrderDTO extends BaseDTO
{
    /**
     * @param OrderItemDTO[] $items
     */
    public function __construct(
        public readonly string $userId,
        public readonly array  $items,        // OrderItemDTO[]
        public readonly string $shippingName,
        public readonly string $addressLine1,
        public readonly string $city,
        public readonly string $postcode,
        public readonly string $country,
        public readonly string $currency      = 'BDT',
        public readonly float  $shippingFee   = 0,
        public readonly ?string $addressLine2 = null,
        public readonly ?string $state        = null,
        public readonly ?string $idempotencyKey
    ) {}

    public static function fromRequest(Request $request): static
    {
        return new static(
            userId:       $request->user()->id,
            items:        array_map(
                fn ($item) => OrderItemDTO::fromArray($item),
                $request->validated('items')
            ),
            shippingName: $request->validated('shipping.name'),
            addressLine1: $request->validated('shipping.address_line1'),
            city:         $request->validated('shipping.city'),
            postcode:     $request->validated('shipping.postcode'),
            country:      $request->validated('shipping.country'),
            currency:     $request->validated('currency', 'BDT'),
            shippingFee:  $request->validated('shipping_fee', 0),
            addressLine2: $request->validated('shipping.address_line2'),
            state:        $request->validated('shipping.state'),
            idempotencyKey: $request->header('Idempotency-Key'),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            userId:       $data['user_id'],
            items:        array_map(
                fn ($item) => OrderItemDTO::fromArray($item),
                $data['items']
            ),
            shippingName: $data['shipping']['name'],
            addressLine1: $data['shipping']['address_line1'],
            city:         $data['shipping']['city'],
            postcode:     $data['shipping']['postcode'],
            country:      $data['shipping']['country'],
            currency:     $data['currency']      ?? 'BDT',
            shippingFee:  $data['shipping_fee']  ?? 0,
            addressLine2: $data['shipping']['address_line2'] ?? null,
            state:        $data['shipping']['state']         ?? null,
            idempotencyKey: $data['idempotency_key'] ?? null,
        );
    }
}