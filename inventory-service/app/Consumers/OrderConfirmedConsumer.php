<?php

namespace App\Consumers;

use App\Services\StockReservationService;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

/** Consumes `order.confirmed` — converts held reservations into permanent stock deductions. */
class OrderConfirmedConsumer
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
            $this->reservations->commit($orderId, $item['sku']);
        }

        Log::info("OrderConfirmedConsumer: committed stock for order {$orderId}");
        $message->ack();
    }
}
