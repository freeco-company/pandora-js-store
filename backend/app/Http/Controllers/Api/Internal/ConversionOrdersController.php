<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-003 §2.2 / §3.2 — expose 母艦 order summary so py-service's
 * `HttpMothershipOrderClient` can resolve the loyalist rule's
 * "≥2 母艦復購 in last 90d" condition.
 *
 * Endpoint: GET /api/internal/conversion/customer-orders/{pandora_user_uuid}
 *
 * Why look up by `pandora_user_uuid` (not customer.id):
 *   - Cross-service ID hygiene — py-service only ever knows the platform UUID.
 *     We deliberately don't leak internal customer.id outwards (fewer tightly
 *     coupled foreign keys = easier future schema changes).
 *
 * Why 90-day window:
 *   - Matches `LOYALIST_LOOKBACK_DAYS = 90` in py-service `lifecycle.py`.
 *   - Keeps query bounded (good for index usage on `orders.created_at`) and
 *     prevents stale buyers from auto-firing loyalist on dormant accounts.
 *
 * What counts as a "purchase":
 *   - Orders in status `processing` or `completed` only. We exclude `pending`
 *     (not yet paid), `cancelled`, `refunded` — those should not count toward
 *     loyalty signals.
 *
 * Auth: signed via `conversion.internal` middleware (HMAC, see
 * VerifyConversionInternalSignature). Missing/wrong sig → 401.
 */
class ConversionOrdersController extends Controller
{
    public function __invoke(string $pandoraUserUuid): JsonResponse
    {
        // Cheap shape validation. Eloquent will accept anything for a string
        // where clause, but we'd rather fail fast on obvious garbage than
        // burn a query. Standard UUID v4/v7 length = 36 chars.
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $pandoraUserUuid)) {
            return response()->json([
                'error' => 'invalid uuid format',
                'reason' => 'bad_uuid',
            ], 422);
        }

        $customer = Customer::query()
            ->where('pandora_user_uuid', $pandoraUserUuid)
            ->first(['id', 'pandora_user_uuid']);

        if ($customer === null) {
            // Distinguish "uuid never seen" from "customer exists but has no
            // orders". py-service uses `reason` to tell stub-fallback vs real-zero
            // apart in logs.
            return response()->json([
                'error' => 'customer not found for uuid',
                'reason' => 'uuid_not_mapped',
            ], 404);
        }

        // Single grouped query: count(*) filtered to 90d, count(*) lifetime,
        // max(created_at). Cheaper than three round-trips and small enough
        // to read clearly.
        $cutoff = Carbon::now()->subDays(90);
        $row = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['processing', 'completed'])
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS recent_orders_90d, '
                .'COUNT(*) AS total_orders, '
                .'MAX(created_at) AS last_order_at',
                [$cutoff]
            )
            ->first();

        // `selectRaw` always returns a row (with NULLs when no orders). Cast
        // defensively because MariaDB returns SUM as string.
        $recent = (int) ($row->recent_orders_90d ?? 0);
        $total = (int) ($row->total_orders ?? 0);
        $lastOrderAt = $row->last_order_at !== null
            ? Carbon::parse($row->last_order_at)->toIso8601String()
            : null;

        return response()->json([
            'pandora_user_uuid' => $pandoraUserUuid,
            'recent_orders_90d' => $recent,
            'total_orders' => $total,
            'last_order_at' => $lastOrderAt,
        ]);
    }
}
