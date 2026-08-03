<?php

namespace App\Services;

use App\DTOs\CreateProductDTO;
use App\Exceptions\ProductNotFoundException;
use App\Models\Product;
use App\Models\Stock;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->products->paginate($filters, $perPage);
    }

    public function findBySku(string $sku): Product
    {
        $product = $this->products->findBySku($sku);

        if (!$product) {
            throw new ProductNotFoundException($sku);
        }

        return $product;
    }

    public function create(CreateProductDTO $dto): Product
    {
        return DB::transaction(function () use ($dto) {
            $product = $this->products->create([
                'sku' => $dto->sku,
                'name' => $dto->name,
                'description' => $dto->description,
                'price' => $dto->price,
                'currency' => $dto->currency,
                'attributes' => $dto->attributes,
            ]);

            Stock::create([
                'sku' => $product->sku,
                'quantity' => $dto->initialQuantity,
                'reserved_quantity' => 0,
            ]);

            return $product->fresh('stock');
        });
    }
}
