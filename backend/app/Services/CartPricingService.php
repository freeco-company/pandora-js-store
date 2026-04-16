<?php

namespace App\Services;

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
     */
    public function calculate(array $cartItems): array
    {
        $productIds = collect($cartItems)->pluck('product_id')->unique()->values();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $items = collect($cartItems)->map(function ($item) use ($products) {
            $product = $products->get($item['product_id']);
            if (!$product) return null;
            return [
                'product_id' => $product->id,
                'product' => $product,
                'quantity' => (int) $item['quantity'],
            ];
        })->filter()->values();

        $totalQuantity = $items->sum('quantity');

        if ($totalQuantity >= 2) {
            $comboTotal = $this->calculateTotal($items, 'combo');

            if ($comboTotal >= self::VIP_THRESHOLD) {
                $vipTotal = $this->calculateTotal($items, 'vip');
                return $this->buildResult('vip', $items, $vipTotal);
            }

            return $this->buildResult('combo', $items, $comboTotal);
        }

        $regularTotal = $this->calculateTotal($items, 'regular');
        return $this->buildResult('regular', $items, $regularTotal);
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

    private function buildResult(string $tier, Collection $items, float $total): array
    {
        return [
            'tier' => $tier,
            'total' => $total,
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
