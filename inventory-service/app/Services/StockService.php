<?php

namespace App\Services;

use App\DTOs\AdjustStockDTO;
use App\Exceptions\ProductNotFoundException;
use App\Models\Stock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Admin stock adjustment (restock, correction, write-off).
     * $dto->delta may be positive or negative.
     */
    public function adjust(AdjustStockDTO $dto): Stock
    {
        $lock = Cache::lock("stock-lock:{$dto->sku}", seconds: 10);

        return $lock->block(5, function () use ($dto) {
            return DB::transaction(function () use ($dto) {
                $stock = Stock::where('sku', $dto->sku)->lockForUpdate()->first();

                if (!$stock) {
                    throw new ProductNotFoundException($dto->sku);
                }

                $newQuantity = $stock->quantity + $dto->delta;

                if ($newQuantity < $stock->reserved_quantity) {
                    throw new \InvalidArgumentException(
                        "Adjustment would drop on-hand ({$newQuantity}) below reserved ({$stock->reserved_quantity}) for SKU [{$dto->sku}]"
                    );
                }

                $stock->update(['quantity' => max(0, $newQuantity)]);

                // NOTE: $dto->reason is accepted but not yet persisted anywhere —
                // worth adding a stock_adjustments audit table if you want a
                // history of *why* quantities changed, not just the current value.

                return $stock->fresh();
            });
        });
    }
}
