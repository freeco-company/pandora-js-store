<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private CartPricingService $pricingService
    ) {}

    /**
     * Calculate cart pricing.
     *
     * POST /api/cart/calculate
     * Body: { "items": [{ "product_id": 1, "quantity": 2 }, ...] }
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $result = $this->pricingService->calculate($request->items);

        return response()->json($result);
    }
}
