<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer wishlist — sanctum-protected. Public endpoints not exposed
 * (guest wishlist lives entirely in localStorage on the client).
 *
 * Bulk-sync exists so a guest who built up a localStorage wishlist can
 * push it server-side on first login without the round-trip cost of
 * one POST per product.
 */
class WishlistController extends Controller
{
    /** GET /api/wishlist — returns full wishlist with embedded product cards. */
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::where('customer_id', $request->user()->id)
            ->with(['product:id,slug,name,image,price,combo_price,vip_price,stock_status,is_active'])
            ->orderByDesc('created_at')
            ->get(['id', 'product_id', 'created_at']);

        return response()->json([
            'items' => $items->filter(fn ($w) => $w->product !== null)->values(),
        ]);
    }

    /** POST /api/wishlist  body: {product_id}  → idempotent add. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id']);

        Wishlist::firstOrCreate(
            ['customer_id' => $request->user()->id, 'product_id' => $data['product_id']],
            ['created_at' => now()],
        );

        return response()->json(['ok' => true]);
    }

    /** DELETE /api/wishlist/{productId} — idempotent remove. */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        Wishlist::where('customer_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/wishlist/sync  body: {product_ids: int[]}
     * Bulk merge — used on first login after a guest built up a localStorage
     * wishlist. Existing entries are kept (firstOrCreate is idempotent).
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => 'required|array|max:200',
            'product_ids.*' => 'integer',
        ]);

        // Filter to existing product IDs only — silently drop unknowns
        $validIds = Product::whereIn('id', $data['product_ids'])->pluck('id');

        $now = now();
        foreach ($validIds as $id) {
            Wishlist::firstOrCreate(
                ['customer_id' => $request->user()->id, 'product_id' => $id],
                ['created_at' => $now],
            );
        }

        return response()->json(['ok' => true, 'merged' => $validIds->count()]);
    }
}
