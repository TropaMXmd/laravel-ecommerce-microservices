<?php

namespace App\Consumers;

use App\Exceptions\InsufficientStockException;
use App\Services\StockReservationService;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consumes `order.placed` from the `orders` topic exchange.
 * For each line item, attempts a stock reservation. If ANY line fails,
 * previously-reserved lines in this order are compensated (released) and
 * a single `stock.insufficient` outcome wins — the order-service will
 * transition the order to `cancelled` on receipt.
 */
class OrderPlacedConsumer
{
    public function __construct(
        private readonly StockReservationService $reservations,
    ) {}

    public function handle(AMQPMessage $message): void
    {
        $payload = json_decode($message->getBody(), true);
        $orderId = $payload['order_id'] ?? null;
        $items = $payload['items'] ?? [];

        if (!$orderId || empty($items)) {
            Log::warning('OrderPlacedConsumer: malformed message, acking without processing', ['payload' => $payload]);
            $message->ack();
            return;
        }

        $reservedSkus = [];

        try {
            foreach ($items as $item) {
                $this->reservations->reserve($orderId, $item['sku'], $item['quantity']);
                $reservedSkus[] = $item['sku'];
            }
        } catch (InsufficientStockException $e) {
            // Compensate: release any lines we already reserved for this order
            foreach ($reservedSkus as $sku) {
                $this->reservations->release($orderId, $sku, reason: 'insufficient_stock_rollback');
            }

            Log::info("OrderPlacedConsumer: order {$orderId} rejected — {$e->getMessage()}");
        }

        // stock.reserved / stock.insufficient events were already recorded to the
        // outbox transactionally inside StockReservationService::reserve(). The
        // PublishOutboxMessagesJob scheduler will flush them to RabbitMQ.
        $message->ack();
    }
}
