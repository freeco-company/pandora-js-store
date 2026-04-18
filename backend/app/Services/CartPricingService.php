<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Product;
use Illuminate\Support\Collection;

class CartPricingService
{
    const VIP_THRESHOLD = 4000;

    /**
     * Calculate cart pricing with bundle promotion support.
     *
     * Incoming items are a mix of:
     *   - product items: ['product_id' => N, 'quantity' => N]
     *   - bundle items:  ['campaign_id' => N, 'quantity' => N, 'type' => 'bundle']
     *
     * Pricing rules:
     *   1. Product items — 3-tier ladder (regular / combo ≥2 / VIP ≥4000)
     *   2. Bundle in cart → every product item also upgrades to VIP tier
     *   3. Bundle price = sum(buy items VIP × qty) × bundle_qty, fixed
     *
     * A bundle whose campaign is no longer running is returned in
     * `unavailable` with reason `bundle_expired` (cart UI should remove).
     */
    public function calculate(array $cartItems): array
    {
        $productInputs = collect($cartItems)->filter(fn ($i) => ($i['type'] ?? 'product') !== 'bundle');
        $bundleInputs = collect($cartItems)->filter(fn ($i) => ($i['type'] ?? null) === 'bundle');

        $unavailable = [];

        // ── Resolve product items ───────────────────────────────────
        $productIds = $productInputs->pluck('product_id')->filter()->unique()->values();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $items = $productInputs->map(function ($item) use ($products, &$unavailable) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                $unavailable[] = ['product_id' => $item['product_id'], 'reason' => 'not_found', 'name' => '未知商品'];
                return null;
            }
            if (!$product->is_active) {
                $unavailable[] = ['product_id' => $product->id, 'reason' => 'inactive', 'name' => $product->name];
                return null;
            }
            if ($product->stock_status === 'outofstock') {
                $unavailable[] = ['product_id' => $product->id, 'reason' => 'out_of_stock', 'name' => $product->name];
                return null;
            }
            $qty = (int) $item['quantity'];
            if ($product->stock_quantity && $qty > $product->stock_quantity) {
                $unavailable[] = [
                    'product_id' => $product->id,
                    'reason' => 'insufficient_stock',
                    'name' => $product->name,
                    'available' => $product->stock_quantity,
                    'requested' => $qty,
                ];
                return null;
            }
            return [
                'product_id' => $product->id,
                'product' => $product,
                'quantity' => $qty,
            ];
        })->filter()->values();

        // ── Resolve bundle items ────────────────────────────────────
        $bundleIds = $bundleInputs->pluck('campaign_id')->filter()->unique()->values();
        $campaigns = $bundleIds->isNotEmpty()
            ? Campaign::with(['buyItems', 'giftItems'])->whereIn('id', $bundleIds)->get()->keyBy('id')
            : collect();

        $bundles = $bundleInputs->map(function ($item) use ($campaigns, &$unavailable) {
            $campaign = $campaigns->get($item['campaign_id']);
            if (!$campaign) {
                $unavailable[] = ['campaign_id' => $item['campaign_id'], 'reason' => 'bundle_not_found', 'name' => '未知套組'];
                return null;
            }
            if (!$campaign->isRunning()) {
                $unavailable[] = ['campaign_id' => $campaign->id, 'reason' => 'bundle_expired', 'name' => $campaign->name];
                return null;
            }
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            return [
                'campaign_id' => $campaign->id,
                'campaign' => $campaign,
                'quantity' => $qty,
                'unit_price' => $campaign->bundlePrice(),
            ];
        })->filter()->values();

        // Nothing purchasable
        if ($items->isEmpty() && $bundles->isEmpty()) {
            return [
                'tier' => 'regular',
                'total' => 0,
                'campaign_vip' => false,
                'items' => [],
                'bundles' => [],
                'unavailable' => $unavailable,
            ];
        }

        // ── Tier resolution ─────────────────────────────────────────
        $hasBundle = $bundles->isNotEmpty();
        $productQty = $items->sum('quantity');

        $tier = 'regular';
        if ($hasBundle) {
            $tier = 'vip';
        } elseif ($productQty >= 2) {
            $tier = 'combo';
            $comboTotal = $this->calculateProductTotal($items, 'combo');
            if ($comboTotal >= self::VIP_THRESHOLD) {
                $tier = 'vip';
            }
        }

        $productTotal = $this->calculateProductTotal($items, $tier);
        $bundleTotal = $bundles->sum(fn ($b) => $b['unit_price'] * $b['quantity']);

        return $this->buildResult($tier, $items, $bundles, $productTotal + $bundleTotal, $hasBundle, $unavailable);
    }

    private function calculateProductTotal(Collection $items, string $tier): float
    {
        return $items->sum(fn ($item) => $this->getPrice($item['product'], $tier) * $item['quantity']);
    }

    public function getPrice(Product $product, string $tier): float
    {
        return match ($tier) {
            'vip' => (float) ($product->vip_price ?? $product->price),
            'combo' => (float) ($product->combo_price ?? $product->price),
            default => (float) $product->price,
        };
    }

    private function buildResult(
        string $tier,
        Collection $items,
        Collection $bundles,
        float $total,
        bool $campaignVip,
        array $unavailable,
    ): array {
        return [
            'tier' => $tier,
            'total' => $total,
            'campaign_vip' => $campaignVip,
            'unavailable' => $unavailable,
            'items' => $items->map(function ($item) use ($tier) {
                $unitPrice = $this->getPrice($item['product'], $tier);
                return [
                    'product_id' => $item['product_id'],
                    'name' => $item['product']->name,
                    'quantity' => $item['quantity'],
                    'original_price' => (float) $item['product']->price,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $item['quantity'],
                    'image' => $item['product']->image,
                ];
            })->values()->toArray(),
            'bundles' => $bundles->map(function ($b) {
                $campaign = $b['campaign'];
                return [
                    'campaign_id' => $b['campaign_id'],
                    'name' => $campaign->name,
                    'slug' => $campaign->slug,
                    'image' => $campaign->image,
                    'quantity' => $b['quantity'],
                    'unit_price' => $b['unit_price'],
                    'subtotal' => $b['unit_price'] * $b['quantity'],
                    'buy_items' => $campaign->buyItems->map(fn ($p) => [
                        'product_id' => $p->id,
                        'name' => $p->name,
                        'image' => $p->image,
                        'quantity' => (int) $p->pivot->quantity,
                    ])->values()->toArray(),
                    'gift_items' => $campaign->giftItems->map(fn ($p) => [
                        'product_id' => $p->id,
                        'name' => $p->name,
                        'image' => $p->image,
                        'quantity' => (int) $p->pivot->quantity,
                    ])->values()->toArray(),
                ];
            })->values()->toArray(),
        ];
    }
}
