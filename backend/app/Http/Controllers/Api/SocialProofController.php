<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Real social proof for a product detail page.
 *
 * Two numbers, both derived from real data — no fabricated counts:
 *   - total_sold: sum(quantity) across paid orders historically
 *   - viewers_now: distinct sessions that hit /products/{slug} in last 15 min
 *
 * Cached 60s to keep the cost flat under bursty product traffic. Below
 * the threshold returns null so the UI can hide entirely (showing
 * "已售 0 件" is anti-trust for a brand-new product).
 */
class SocialProofController extends Controller
{
    private const TOTAL_SOLD_FLOOR = 10;
    private const VIEWERS_FLOOR    = 2;
    private const VIEWERS_WINDOW_MIN = 15;
    private const CACHE_TTL_SEC    = 60;

    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->first(['id', 'slug']);
        if (!$product) {
            return response()->json(['total_sold' => null, 'viewers_now' => null]);
        }

        $totalSold = Cache::remember("social_proof:sold:{$product->id}", self::CACHE_TTL_SEC, function () use ($product) {
            return (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.product_id', $product->id)
                ->whereIn('orders.status', ['processing', 'completed'])
                ->where('orders.payment_status', 'paid')
                ->sum('order_items.quantity');
        });

        $viewersNow = Cache::remember("social_proof:viewers:{$product->id}", self::CACHE_TTL_SEC, function () use ($slug) {
            return (int) DB::table('page_views')
                ->where('path', "/products/{$slug}")
                ->where('created_at', '>', now()->subMinutes(self::VIEWERS_WINDOW_MIN))
                ->distinct('session_id')
                ->count('session_id');
        });

        return response()->json([
            // Hide tiny numbers — UI checks for null and renders nothing
            'total_sold'  => $totalSold >= self::TOTAL_SOLD_FLOOR ? $totalSold : null,
            'viewers_now' => $viewersNow >= self::VIEWERS_FLOOR ? $viewersNow : null,
            'window_min'  => self::VIEWERS_WINDOW_MIN,
        ]);
    }
}
