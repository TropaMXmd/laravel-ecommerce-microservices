<?php

namespace App\Services;

use App\DTOs\PlaceOrderDTO;
use App\Exceptions\OrderNotFoundException;
use App\Models\Order;
use App\Models\OrderOutbox;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orders->paginate($filters, $perPage);
    }

    public function findByUuid(string $uuid): Order
    {
        $order = $this->orders->findByUuid($uuid);

        if (!$order) {
            throw new OrderNotFoundException($uuid);
        }

        return $order;
    }

    /**
     * Places a new order and records the order.placed outbox event in the
     * SAME transaction — either both exist or neither does. Order starts
     * in 'pending'; it only becomes 'confirmed' once inventory-service's
     * stock.reserved event comes back (see StockReservedConsumer).
     */
    public function place(PlaceOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($dto->items as $item) {
                $lineTotal = round($item['unit_price'] * $item['quantity'], 2);
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'sku' => $item['sku'],
                    'product_name' => $item['product_name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ];
            }

            $tax = round($subtotal * $dto->taxRate, 2);
            $total = round($subtotal + $tax + $dto->shippingFee, 2);

            $order = $this->orders->create([
                'user_id' => $dto->userId,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_fee' => $dto->shippingFee,
                'total' => $total,
                'currency' => $dto->currency,
                'shipping_name' => $dto->shippingName,
                'shipping_address_line1' => $dto->shippingAddressLine1,
                'shipping_address_line2' => $dto->shippingAddressLine2,
                'shipping_city' => $dto->shippingCity,
                'shipping_state' => $dto->shippingState,
                'shipping_postcode' => $dto->shippingPostcode,
                'shipping_country' => $dto->shippingCountry,
            ]);

            $order->items()->createMany($lineItems);

            $this->recordOutboxEvent($order, 'order.placed', 'orders', 'order.placed', [
                'order_id' => $order->uuid,
                'user_id' => $order->user_id,
                'items' => array_map(fn ($i) => [
                    'sku' => $i['sku'],
                    'quantity' => $i['quantity'],
                ], $lineItems),
            ]);

            return $order->fresh('items');
        });
    }

    /**
     * Called by StockReservedConsumer when inventory confirms stock is held.
     * Idempotent: no-op if the order isn't in 'pending' (already handled).
     */
    public function markConfirmed(string $orderUuid): void
    {
        DB::transaction(function () use ($orderUuid) {
            $order = Order::where('uuid', $orderUuid)->lockForUpdate()->first();

            if (!$order || $order->status !== 'pending') {
                return; // already processed, or unknown order — safe no-op
            }

            $this->stateMachine->transition($order, 'confirmed');
            $order->save();

            $this->recordOutboxEvent($order, 'order.confirmed', 'orders', 'order.confirmed', [
                'order_id' => $order->uuid,
                'items' => $order->items->map(fn ($i) => [
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                ])->all(),
            ]);
        });
    }

    /**
     * Called by StockInsufficientConsumer when inventory rejects the order
     * for lack of stock. Idempotent: no-op if not 'pending'.
     */
    public function markCancelledDueToStock(string $orderUuid): void
    {
        DB::transaction(function () use ($orderUuid) {
            $order = Order::where('uuid', $orderUuid)->lockForUpdate()->first();

            if (!$order || $order->status !== 'pending') {
                return;
            }

            $this->stateMachine->transition($order, 'cancelled', 'Insufficient stock');
            $order->save();

            $this->recordOutboxEvent($order, 'order.cancelled', 'orders', 'order.cancelled', [
                'order_id' => $order->uuid,
                'items' => $order->items->map(fn ($i) => [
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                ])->all(),
                'reason' => 'insufficient_stock',
            ]);
        });
    }

    /**
     * Customer/admin-initiated cancellation via the HTTP endpoint.
     * Only allowed while isCancellable() (pending or confirmed).
     */
    public function cancel(Order $order, ?string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $this->stateMachine->transition($order, 'cancelled', $reason);
            $order->save();

            $this->recordOutboxEvent($order, 'order.cancelled', 'orders', 'order.cancelled', [
                'order_id' => $order->uuid,
                'items' => $order->items->map(fn ($i) => [
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                ])->all(),
                'reason' => $reason ?? 'customer_requested',
            ]);

            return $order->fresh('items');
        });
    }

    private function recordOutboxEvent(Order $order, string $eventType, string $exchange, string $routingKey, array $payload): void
    {
        OrderOutbox::create([
            'aggregate_type' => 'Order',
            'aggregate_id' => $order->id,
            'event_type' => $eventType,
            'exchange' => $exchange,
            'routing_key' => $routingKey,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }
}