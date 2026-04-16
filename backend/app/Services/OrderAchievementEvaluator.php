<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;

/**
 * Evaluates which achievements an order unlocks for a customer.
 * Mutates customer aggregate counters (total_orders, total_spent).
 */
class OrderAchievementEvaluator
{
    public function __construct(private AchievementService $achievements)
    {
    }

    /**
     * Evaluate after order created; returns newly-awarded codes.
     */
    public function evaluate(Customer $customer, Order $order, bool $usedCoupon): array
    {
        // Refresh aggregate counters from DB (don't rely on in-memory state)
        $paidOrders = $customer->orders()->count();
        $totalSpent = (int) $customer->orders()->sum('total');

        $customer->update([
            'total_orders' => $paidOrders,
            'total_spent' => $totalSpent,
        ]);

        $codes = [];

        // Activation + first order
        $this->achievements->markActivation($customer, 'first_order');
        $codes[] = AchievementCatalog::FIRST_ORDER;

        // Repeat purchase milestones
        if ($paidOrders >= 3) $codes[] = AchievementCatalog::ORDER_3;
        if ($paidOrders >= 5) $codes[] = AchievementCatalog::ORDER_5;
        if ($paidOrders >= 10) $codes[] = AchievementCatalog::ORDER_10;

        // Spending milestones
        if ($totalSpent >= 1000) $codes[] = AchievementCatalog::SPEND_1K;
        if ($totalSpent >= 5000) $codes[] = AchievementCatalog::SPEND_5K;
        if ($totalSpent >= 10000) $codes[] = AchievementCatalog::SPEND_10K;

        // Tier discovery
        if ($order->pricing_tier === 'combo') {
            $codes[] = AchievementCatalog::UNLOCK_COMBO;
        } elseif ($order->pricing_tier === 'vip') {
            $codes[] = AchievementCatalog::UNLOCK_VIP;
            $vipCount = $customer->orders()->where('pricing_tier', 'vip')->count();
            if ($vipCount >= 3) $codes[] = AchievementCatalog::VIP_3;
        }

        // Category exploration — find categories of all products in this order
        $productIds = $order->items->pluck('product_id')->unique();
        $categorySlugs = Product::whereIn('id', $productIds)
            ->with('categories:id,slug')
            ->get()
            ->flatMap(fn ($p) => $p->categories->pluck('slug'))
            ->unique()
            ->values();

        foreach ($categorySlugs as $slug) {
            $codes[] = match ($slug) {
                'slimming' => AchievementCatalog::EXPLORE_SLIMMING,
                'health' => AchievementCatalog::EXPLORE_HEALTH,
                'beauty' => AchievementCatalog::EXPLORE_BEAUTY,
                default => null,
            };
        }
        $codes = array_filter($codes);

        // All three categories ever purchased?
        $everPurchasedCategories = $customer->orders()
            ->with('items.product.categories:id,slug')
            ->get()
            ->flatMap(fn ($o) => $o->items)
            ->flatMap(fn ($i) => $i->product?->categories?->pluck('slug') ?? collect())
            ->unique()
            ->values();
        if ($everPurchasedCategories->count() >= 3) {
            $codes[] = AchievementCatalog::EXPLORE_ALL;
        }

        // Coupon usage
        if ($usedCoupon) {
            $codes[] = AchievementCatalog::FIRST_COUPON;
        }

        return $this->achievements->awardMany($customer, $codes);
    }
}
