<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartPricingTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'P',
            'slug' => 'p-' . uniqid(),
            'price' => 1000,
            'combo_price' => 900,
            'vip_price' => 800,
            'is_active' => true,
        ], $overrides));
    }

    public function test_single_item_is_regular_tier(): void
    {
        $p = $this->product();

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ]);

        $res->assertOk()
            ->assertJsonPath('tier', 'regular')
            ->assertJsonPath('total', 1000);
    }

    public function test_two_items_trigger_combo_tier(): void
    {
        $p = $this->product();

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 2]],
        ]);

        $res->assertOk()
            ->assertJsonPath('tier', 'combo')
            ->assertJsonPath('total', 1800);
    }

    public function test_vip_tier_when_combo_total_reaches_threshold(): void
    {
        // 5 × 900 = 4500 ≥ 4000 → VIP
        $p = $this->product();

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 5]],
        ]);

        $res->assertOk()
            ->assertJsonPath('tier', 'vip')
            ->assertJsonPath('total', 4000);
    }

    public function test_mixed_products_share_highest_applicable_tier(): void
    {
        $p1 = $this->product(['price' => 1000, 'combo_price' => 900, 'vip_price' => 800]);
        $p2 = $this->product(['price' => 2000, 'combo_price' => 1800, 'vip_price' => 1600]);

        // combo total: 900 + 1800 = 2700 → combo, not vip
        $res = $this->postJson('/api/cart/calculate', [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 1],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
        ]);

        $res->assertOk()->assertJsonPath('tier', 'combo');
    }

    public function test_missing_combo_price_falls_back_to_regular_price(): void
    {
        $p = $this->product(['price' => 500, 'combo_price' => null, 'vip_price' => null]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 3]],
        ]);

        $res->assertOk()
            ->assertJsonPath('tier', 'combo')
            ->assertJsonPath('total', 1500);
    }

    public function test_validation_rejects_empty_items(): void
    {
        $this->postJson('/api/cart/calculate', ['items' => []])
            ->assertStatus(422);
    }

    public function test_validation_rejects_nonexistent_product(): void
    {
        $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => 99999, 'quantity' => 1]],
        ])->assertStatus(422);
    }
}
