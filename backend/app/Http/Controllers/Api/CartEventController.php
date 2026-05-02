<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartEvent;
use App\Models\Visit;
use App\Services\InternalTrafficDetector;
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
        // Checkout sub-steps — pinpoint where the cart→paid drop-off happens.
        // The /cart→/checkout overall drop is ~80%; without these we can't
        // tell if it's form abandonment, payment selection friction, or
        // submit failures (validation / ECPay redirect / etc.).
        'checkout_form_filled', 'checkout_payment_selected',
        'checkout_submit_attempt', 'checkout_submit_failed',
    ];

    public function store(Request $request, InternalTrafficDetector $internal): JsonResponse
    {
        $data = $request->validate([
            'session_id'   => 'nullable|string|max:64',
            'event_type'   => 'required|string|in:' . implode(',', self::EVENT_TYPES),
            'product_id'   => 'nullable|integer|exists:products,id',
            'bundle_id'    => 'nullable|integer|exists:bundles,id',
            'quantity'     => 'nullable|integer|min:1|max:999',
            'value'        => 'nullable|numeric|min:0|max:9999999',
        ]);

        // Mirror VisitController's internal-traffic flagging. Three signals,
        // any of which is sufficient:
        //   1. Authenticated as an internal email
        //   2. session_id has a prior internal visit row today (same browser
        //      that VisitController already flagged on a page view)
        //   3. Source IP matches the internal list (for unauth'd events)
        // Cart events arrive after the page-view ping that opens the session,
        // so #2 is the most reliable case in practice; #3 is a belt-and-braces
        // fallback for direct API calls.
        $sessionId = $data['session_id'] ?? null;
        $isInternal = $internal->isInternalCustomerId($request->user()?->id);
        if (! $isInternal && $sessionId) {
            $isInternal = Visit::where('session_id', $sessionId)
                ->where('is_internal', true)
                ->exists();
        }
        if (! $isInternal) {
            $ip = $request->header('CF-Connecting-IP')
                ?? $request->header('X-Forwarded-For')
                ?? $request->ip();
            $isInternal = $internal->isInternalIp($ip);
        }

        CartEvent::create([
            'session_id'  => $sessionId,
            'customer_id' => $request->user()?->id,
            'is_internal' => $isInternal,
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
