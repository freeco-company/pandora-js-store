<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ADR-008 §2.3 — feeds py-service the signal source for
 * `franchisee_self_use → franchisee_active` (a):
 *   "月進貨連續 N 個月 > 門檻 → 標記 active"
 *
 * Endpoint: GET /api/internal/conversion/customer-monthly-purchases/{pandora_user_uuid}?months=3
 *
 * Why month-by-month (not a single rolling 90d sum):
 *   - The rule is "**連續** N 個月 > NT$30K", not "90d total". py-service needs
 *     per-month totals to apply the consecutive-months check itself.
 *
 * Months returned:
 *   - Always exactly `months` rows, newest-first, including months with zero
 *     orders (so py-service doesn't have to fill gaps). Default 3, max 12.
 *   - "Month" = calendar month in app TZ (Asia/Taipei via Carbon::now()), not
 *     a 30-day rolling window. Matches how 婕樂纖's accounting frames it.
 *
 * What counts:
 *   - Orders with `payment_status='paid'` (this is the actual source of truth
 *     for revenue widgets — see RevenueChart / StatsOverview).
 *   - Excludes pending/cancelled/refunded by definition (payment_status != 'paid').
 *
 * Auth: signed via `conversion.internal` middleware (same HMAC pattern as
 * ConversionOrdersController). Missing/wrong sig → 401.
 */
class ConversionMonthlyPurchasesController extends Controller
{
    private const DEFAULT_MONTHS = 3;

    private const MAX_MONTHS = 12;

    public function __invoke(Request $request, string $pandoraUserUuid): JsonResponse
    {
        // Same cheap UUID shape check as ConversionOrdersController. Saves a DB
        // round-trip on obvious garbage.
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $pandoraUserUuid)) {
            return response()->json([
                'error' => 'invalid uuid format',
                'reason' => 'bad_uuid',
            ], 422);
        }

        $months = (int) $request->query('months', self::DEFAULT_MONTHS);
        if ($months < 1) {
            $months = self::DEFAULT_MONTHS;
        }
        if ($months > self::MAX_MONTHS) {
            $months = self::MAX_MONTHS;
        }

        $customer = Customer::query()
            ->where('pandora_user_uuid', $pandoraUserUuid)
            ->first(['id', 'pandora_user_uuid']);

        if ($customer === null) {
            return response()->json([
                'error' => 'customer not found for uuid',
                'reason' => 'uuid_not_mapped',
            ], 404);
        }

        // Build the [start, end] window covering the requested calendar months.
        // Example for months=3 on 2026-04-28: window = [2026-02-01, 2026-05-01).
        $now = Carbon::now();
        $windowEnd = $now->copy()->startOfMonth()->addMonth();           // exclusive
        $windowStart = $now->copy()->startOfMonth()->subMonths($months - 1); // inclusive

        // Pull paid orders in window then bucket by calendar month in PHP.
        // We avoid MariaDB-only DATE_FORMAT() because tests run on sqlite, and
        // the dataset is bounded (one customer × ≤ 12 months) so the in-PHP
        // bucketing is negligible. created_at index handles the range filter.
        $orders = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->select('created_at', 'total')
            ->get();

        $buckets = []; // ['2026-04' => ['total' => ..., 'order_count' => ...], ...]
        foreach ($orders as $o) {
            $key = Carbon::parse($o->created_at)->format('Y-m');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['total' => 0.0, 'order_count' => 0];
            }
            $buckets[$key]['total'] += (float) $o->total;
            $buckets[$key]['order_count'] += 1;
        }

        // Fill missing months with zeros, newest-first.
        $monthlyTotals = [];
        for ($i = 0; $i < $months; $i++) {
            $bucket = $now->copy()->startOfMonth()->subMonths($i);
            $key = $bucket->format('Y-m');
            $row = $buckets[$key] ?? null;

            $monthlyTotals[] = [
                'month' => $key,
                'total' => $row !== null ? round($row['total'], 2) : 0.0,
                'order_count' => $row !== null ? $row['order_count'] : 0,
            ];
        }

        return response()->json([
            'uuid' => $pandoraUserUuid,
            'customer_id' => $customer->id,
            'months_requested' => $months,
            'monthly_totals' => $monthlyTotals,
        ]);
    }
}
