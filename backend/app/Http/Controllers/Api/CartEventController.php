<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cart-event logger. Fire-and-forget POST from CartProvider alongside
 * the existing GTM dataLayer pushes. Lets the backend compute funnel
 * rates (add-to-cart rate, checkout initiation rate) without needing
 * GA4 Reporting API credentials.
 *
 * Not authenticated — cart activity happens before login. We take
 * customer_id from the auth'd user if present, ignore any client claim.
 * session_id ties back to `visits.session_id` for cross-table joins.
 */
class CartEventController extends Controller
{
    private const EVENT_TYPES = [
        'view_item', 'add_to_cart', 'remove_from_cart', 'begin_checkout', 'purchase',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id'   => 'nullable|string|max:64',
            'event_type'   => 'required|string|in:' . implode(',', self::EVENT_TYPES),
            'product_id'   => 'nullable|integer|exists:products,id',
            'bundle_id'    => 'nullable|integer|exists:bundles,id',
            'quantity'     => 'nullable|integer|min:1|max:999',
            'value'        => 'nullable|numeric|min:0|max:9999999',
        ]);

        CartEvent::create([
            'session_id'  => $data['session_id'] ?? null,
            'customer_id' => $request->user()?->id,
            'event_type'  => $data['event_type'],
            'product_id'  => $data['product_id'] ?? null,
            'bundle_id'   => $data['bundle_id'] ?? null,
            'quantity'    => $data['quantity'] ?? 1,
            'value'       => $data['value'] ?? null,
            'occurred_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
