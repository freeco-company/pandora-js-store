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
     * Calculate cart pricing. Accepts mixed product + bundle items.
     *
     * POST /api/cart/calculate
     * Body: { "items": [
     *   { "product_id": 1, "quantity": 2 },
     *   { "type": "bundle", "campaign_id": 5, "quantity": 1 }
     * ] }
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.type' => 'nullable|string|in:product,bundle',
            // product_id required unless type is bundle
            'items.*.product_id' => 'required_unless:items.*.type,bundle|nullable|integer|exists:products,id',
            'items.*.campaign_id' => 'required_if:items.*.type,bundle|nullable|integer|exists:campaigns,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $result = $this->pricingService->calculate($request->items);

        return response()->json($result);
    }
}
