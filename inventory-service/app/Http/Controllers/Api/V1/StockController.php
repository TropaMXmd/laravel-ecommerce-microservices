<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\AdjustStockDTO;
use App\Http\Requests\AdjustStockRequest;
use App\Services\StockService;
use Ecomstarter\Core\Response\ApiResponseTrait;
use Illuminate\Routing\Controller;

class StockController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly StockService $stock,
    ) {
    }

    public function adjust(AdjustStockRequest $request)
    {
        $stock = $this->stock->adjust(AdjustStockDTO::fromRequest($request));

        return $this->success([
            'sku' => $stock->sku,
            'quantity' => $stock->quantity,
            'reserved' => $stock->reserved_quantity,
            'available' => $stock->available,
        ], message: 'Stock adjusted');
    }
}
