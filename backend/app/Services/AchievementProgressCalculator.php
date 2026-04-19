<?php

namespace App\Services;

use App\Models\Customer;

/**
 * Computes "current progress" toward each progress-bearing achievement.
 *
 * Used by the customer dashboard to render「1/3 筆訂單」style hints next
 * to unearned achievements. Every value returned is capped at the target
 * so the UI can render `current/target` directly without min() calls.
 *
 * The catalog declares the progress shape; this class is the single
 * place that knows how to evaluate each `type`.
 */
class AchievementProgressCalculator
{
    /**
     * Returns map of [code => ['current' => int, 'target' => int]] for every
     * achievement in the catalog that has a `progress` definition.
     * Earned achievements are still included — caller decides whether to render.
     *
     * Counters are read in 1 query per type (not per code) so this stays cheap.
     */
    public function forCustomer(Customer $customer): array
    {
        $catalog = AchievementCatalog::all();

        // Aggregate values by type — compute lazily, cache per-call.
        $cache = [];
        $valueFor = function (string $type) use (&$cache, $customer): int {
            return $cache[$type] ??= $this->computeValue($customer, $type);
        };

        $out = [];
        foreach ($catalog as $code => $def) {
            $progress = $def['progress'] ?? null;
            if (!$progress) continue;

            $target = (int) $progress['target'];
            $current = min($valueFor($progress['type']), $target);
            $out[$code] = ['current' => $current, 'target' => $target];
        }
        return $out;
    }

    private function computeValue(Customer $customer, string $type): int
    {
        return match ($type) {
            'order_count'     => (int) $customer->total_orders,
            'spend_total'     => (int) $customer->total_spent,
            'vip_order_count' => $customer->orders()->where('pricing_tier', 'vip')->count(),
            'streak_days'     => (int) $customer->streak_days,
            'referral_count'  => Customer::where('referred_by_customer_id', $customer->id)
                                          ->where('referral_reward_granted', true)
                                          ->count(),
            'category_count'  => $customer->orders()
                                          ->with('items.product.categories:id,slug')
                                          ->get()
                                          ->flatMap(fn ($o) => $o->items)
                                          ->flatMap(fn ($i) => $i->product?->categories?->pluck('slug') ?? collect())
                                          ->unique()
                                          ->count(),
            default           => 0,
        };
    }
}
