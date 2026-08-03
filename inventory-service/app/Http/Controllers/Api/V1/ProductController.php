<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CreateProductDTO;
use App\Http\Requests\CreateProductRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Ecomstarter\Core\Response\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ProductService $products,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'is_active']);
        $products = $this->products->list($filters, (int) $request->get('per_page', 15));

        return $this->paginated(
            $products->through(fn ($product) => new ProductResource($product))
        );
    }

    public function show(string $sku)
    {
        $product = $this->products->findBySku($sku);

        return $this->success(new ProductResource($product));
    }

    public function store(CreateProductRequest $request)
    {
        $product = $this->products->create(CreateProductDTO::fromRequest($request));

        return $this->created(new ProductResource($product));
    }
}
