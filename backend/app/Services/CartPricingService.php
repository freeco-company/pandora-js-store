<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Product;
use Illuminate\Support\Collection;

class CartPricingService
{
    const VIP_THRESHOLD = 4000;

    /**
     * Calculate cart pricing based on 3-tier logic:
     * 1. Regular price (total quantity = 1)
     * 2. Combo price (total quantity >= 2, same or different products)
     * 3. VIP price (combo total >= 4000)
     *
     * Campaign override: if ANY item in the cart belongs to an active
     * campaign, the entire cart jumps to VIP tier regardless of total.
     */
    public function calculate(array $cartItems): array
    {
        $productIds = collect($cartItems)->pluck('product_id')->unique()->values();
        $products = Product::with('campaigns')->whereIn('id', $productIds)->get()->keyBy('id');

        $unavailable = [];
        $items = collect($cartItems)->map(function ($item) use ($products, &$unavailable) {
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

        // If all items are unavailable, return early
        if ($items->isEmpty()) {
            return [
                'tier' => 'regular',
                'total' => 0,
                'campaign_vip' => false,
                'items' => [],
                'unavailable' => $unavailable,
            ];
        }

        // Campaign VIP override: any cart item in an active campaign → VIP
        $hasCampaignItem = $this->hasActiveCampaignItem($productIds);
        if ($hasCampaignItem && $items->sum('quantity') >= 2) {
            $vipTotal = $this->calculateTotal($items, 'vip');
            return $this->buildResult('vip', $items, $vipTotal, true, $unavailable);
        }

        $totalQuantity = $items->sum('quantity');

        if ($totalQuantity >= 2) {
            $comboTotal = $this->calculateTotal($items, 'combo');

            if ($comboTotal >= self::VIP_THRESHOLD) {
                $vipTotal = $this->calculateTotal($items, 'vip');
                return $this->buildResult('vip', $items, $vipTotal, false, $unavailable);
            }

            return $this->buildResult('combo', $items, $comboTotal, false, $unavailable);
        }

        $regularTotal = $this->calculateTotal($items, 'regular');
        return $this->buildResult('regular', $items, $regularTotal, false, $unavailable);
    }

    /** Check if any of the given product IDs belong to a currently-running campaign. */
    private function hasActiveCampaignItem(Collection $productIds): bool
    {
        return Campaign::active()
            ->whereHas('products', fn ($q) => $q->whereIn('products.id', $productIds))
            ->exists();
    }

    private function calculateTotal(Collection $items, string $tier): float
    {
        return $items->sum(function ($item) use ($tier) {
            return $this->getPrice($item['product'], $tier) * $item['quantity'];
        });
    }

    public function getPrice(Product $product, string $tier): float
    {
        return match ($tier) {
            'vip' => (float) ($product->vip_price ?? $product->price),
            'combo' => (float) ($product->combo_price ?? $product->price),
            default => (float) $product->price,
        };
    }

    private function buildResult(string $tier, Collection $items, float $total, bool $campaignVip = false, array $unavailable = []): array
    {
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
        ];
    }
}
