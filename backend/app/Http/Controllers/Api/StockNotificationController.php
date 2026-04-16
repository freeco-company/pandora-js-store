<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockNotificationController extends Controller
{
    /**
     * POST /api/products/{slug}/notify-stock
     * Body: { email }  (optional if authenticated)
     */
    public function subscribe(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $email = $request->user()?->email
            ?? $request->validate(['email' => 'required|email'])['email'];

        $sub = StockNotification::firstOrCreate(
            ['product_id' => $product->id, 'email' => $email],
            [
                'customer_id' => $request->user()?->id,
                'notified_at' => null,
            ],
        );

        return response()->json([
            'subscribed' => true,
            'already_existed' => ! $sub->wasRecentlyCreated,
        ]);
    }
}
