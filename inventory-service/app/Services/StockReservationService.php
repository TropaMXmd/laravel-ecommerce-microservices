<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryOutbox;
use App\Models\Stock;
use App\Models\StockReservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StockReservationService
{
    private const LOCK_TIMEOUT_SECONDS = 10;
    private const LOCK_WAIT_SECONDS = 5;
    private const RESERVATION_TTL_MINUTES = 15;

    /**
     * Reserve stock for a single order line. Called per SKU from the order.placed consumer.
     * Redis distributed lock prevents two concurrent reservations racing on the same SKU;
     * SELECT FOR UPDATE prevents the same race at the DB row level as a second line of defense.
     */
    public function reserve(string $orderId, string $sku, int $quantity): StockReservation
    {
        $lock = Cache::lock("stock-lock:{$sku}", self::LOCK_TIMEOUT_SECONDS);

        return $lock->block(self::LOCK_WAIT_SECONDS, function () use ($orderId, $sku, $quantity) {
            return DB::transaction(function () use ($orderId, $sku, $quantity) {
                $stock = Stock::where('sku', $sku)->lockForUpdate()->first();

                if (!$stock || $stock->available < $quantity) {
                    $this->recordOutboxEvent(
                        aggregateId: $orderId,
                        eventType: 'stock.insufficient',
                        routingKey: 'stock.insufficient',
                        payload: [
                            'order_id' => $orderId,
                            'sku' => $sku,
                            'requested' => $quantity,
                            'available' => $stock->available ?? 0,
                        ],
                    );

                    throw new InsufficientStockException($sku, $quantity, $stock->available ?? 0);
                }

                $stock->increment('reserved_quantity', $quantity);

                $reservation = StockReservation::create([
                    'order_id' => $orderId,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'status' => StockReservation::STATUS_HELD,
                    'expires_at' => now()->addMinutes(self::RESERVATION_TTL_MINUTES),
                ]);

                $this->recordOutboxEvent(
                    aggregateId: $reservation->id,
                    eventType: 'stock.reserved',
                    routingKey: 'stock.reserved',
                    payload: [
                        'order_id' => $orderId,
                        'sku' => $sku,
                        'quantity' => $quantity,
                        'expires_at' => $reservation->expires_at->toIso8601String(),
                    ],
                );

                return $reservation;
            });
        });
    }

    /** Order confirmed / shipped: convert a held reservation into a permanent deduction. */
    public function commit(string $orderId, string $sku): void
    {
        DB::transaction(function () use ($orderId, $sku) {
            $reservation = StockReservation::where('order_id', $orderId)
                ->where('sku', $sku)
                ->where('status', StockReservation::STATUS_HELD)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                return; // already committed/released — idempotent no-op
            }

            $stock = Stock::where('sku', $sku)->lockForUpdate()->first();

            $stock->decrement('quantity', $reservation->quantity);
            $stock->decrement('reserved_quantity', $reservation->quantity);

            $reservation->update(['status' => StockReservation::STATUS_COMMITTED]);
        });
    }

    /** Order cancelled / expired: give the reserved units back to the pool (compensation). */
    public function release(string $orderId, string $sku, string $reason = 'order_cancelled'): void
    {
        DB::transaction(function () use ($orderId, $sku, $reason) {
            $reservation = StockReservation::where('order_id', $orderId)
                ->where('sku', $sku)
                ->where('status', StockReservation::STATUS_HELD)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                return;
            }

            Stock::where('sku', $sku)->decrement('reserved_quantity', $reservation->quantity);

            $reservation->update(['status' => StockReservation::STATUS_RELEASED]);

            $this->recordOutboxEvent(
                aggregateId: $reservation->id,
                eventType: 'stock.released',
                routingKey: 'stock.released',
                payload: [
                    'order_id' => $orderId,
                    'sku' => $sku,
                    'quantity' => $reservation->quantity,
                    'reason' => $reason,
                ],
            );
        });
    }

    /**
     * Called every minute by the scheduler (ReleaseExpiredReservationsJob).
     * Sweeps abandoned holds — e.g. an order that never got confirmed within 15 minutes.
     */
    public function releaseExpired(int $batchSize = 100): int
    {
        $expired = StockReservation::where('status', StockReservation::STATUS_HELD)
            ->where('expires_at', '<', now())
            ->limit($batchSize)
            ->get();
 
        $count = 0;

        foreach ($expired as $reservation) {
            $lock = Cache::lock("stock-lock:{$reservation->sku}", self::LOCK_TIMEOUT_SECONDS);

            $lock->block(self::LOCK_WAIT_SECONDS, function () use ($reservation, &$count) {
                DB::transaction(function () use ($reservation, &$count) {
                    $fresh = StockReservation::where('id', $reservation->id)
                        ->where('status', StockReservation::STATUS_HELD)
                        ->lockForUpdate()
                        ->first();

                    if (!$fresh) {
                        return;
                    }

                    Stock::where('sku', $fresh->sku)->decrement('reserved_quantity', $fresh->quantity);
                    $fresh->update(['status' => StockReservation::STATUS_EXPIRED]);
                    $count++;
                });
            });
        }

        if ($count > 0) {
            Log::info("ReleaseExpiredReservations: released {$count} expired holds");
        }

        return $count;
    }

    private function recordOutboxEvent(string $aggregateId, string $eventType, string $routingKey, array $payload): void
    {
        InventoryOutbox::create([
            'aggregate_type' => 'stock_reservation',
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'routing_key' => $routingKey,
            'payload' => $payload,
            'published' => false,
            'attempts' => 0,
        ]);
    }
}
