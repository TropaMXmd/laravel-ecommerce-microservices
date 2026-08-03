<?php

namespace App\Consumers;

use App\Services\StockReservationService;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

/** Consumes `order.cancelled` — releases any still-held reservations back to available stock. */
class OrderCancelledConsumer
{
    public function __construct(
        private readonly StockReservationService $reservations,
    ) {}

    public function handle(AMQPMessage $message): void
    {
        $payload = json_decode($message->getBody(), true);
        $orderId = $payload['order_id'] ?? null;
        $items = $payload['items'] ?? [];

        if (!$orderId) {
            $message->ack();
            return;
        }

        foreach ($items as $item) {
            $this->reservations->release($orderId, $item['sku'], reason: 'order_cancelled');
        }

        Log::info("OrderCancelledConsumer: released stock for order {$orderId}");
        $message->ack();
    }
}
