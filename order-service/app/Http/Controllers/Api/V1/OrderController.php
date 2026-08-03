<?php

use App\Http\Controllers\Controller;
use Ecomstarter\Core\Response\ApiResponseTrait;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getOrders($request->user());

        return $this->successResponse($orders);
    }
}