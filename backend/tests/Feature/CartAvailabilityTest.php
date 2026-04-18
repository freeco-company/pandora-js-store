<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0: Cart availability — deactivated, out-of-stock, insufficient stock, campaign products.
 */
class CartAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function activeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'slug' => 'test-' . uniqid(),
            'price' => 1000,
            'combo_price' => 900,
            'vip_price' => 800,
            'is_active' => true,
            'stock_status' => 'instock',
        ], $overrides));
    }

    // ── P0-1: Deactivated product blocked ──────────────────────

    public function test_cart_rejects_inactive_product(): void
    {
        $p = $this->activeProduct(['is_active' => false]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ]);

        $res->assertOk()
            ->assertJsonPath('unavailable.0.reason', 'inactive')
            ->assertJsonCount(0, 'items');
    }

    // ── P0-2: Out-of-stock product blocked ─────────────────────

    public function test_cart_rejects_out_of_stock_product(): void
    {
        $p = $this->activeProduct(['stock_status' => 'outofstock']);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
        ]);

        $res->assertOk()
            ->assertJsonPath('unavailable.0.reason', 'out_of_stock')
            ->assertJsonCount(0, 'items');
    }

    // ── P0-3: Insufficient stock blocked ───────────────────────

    public function test_cart_rejects_insufficient_stock(): void
    {
        $p = $this->activeProduct(['stock_quantity' => 3]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 5]],
        ]);

        $res->assertOk()
            ->assertJsonPath('unavailable.0.reason', 'insufficient_stock')
            ->assertJsonPath('unavailable.0.available', 3)
            ->assertJsonPath('unavailable.0.requested', 5);
    }

    public function test_cart_allows_quantity_within_stock(): void
    {
        $p = $this->activeProduct(['stock_quantity' => 3]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 3]],
        ]);

        $res->assertOk()
            ->assertJsonCount(0, 'unavailable')
            ->assertJsonCount(1, 'items');
    }

    public function test_cart_allows_untracked_stock(): void
    {
        // stock_quantity = 0 or null means untracked — no limit
        $p = $this->activeProduct(['stock_quantity' => 0]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['product_id' => $p->id, 'quantity' => 99]],
        ]);

        $res->assertOk()
            ->assertJsonCount(0, 'unavailable')
            ->assertJsonCount(1, 'items');
    }

    // ── Mixed cart: some available, some not ────────────────────

    public function test_cart_returns_partial_unavailable(): void
    {
        $good = $this->activeProduct(['name' => 'Available']);
        $bad = $this->activeProduct(['name' => 'Gone', 'is_active' => false]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [
                ['product_id' => $good->id, 'quantity' => 1],
                ['product_id' => $bad->id, 'quantity' => 1],
            ],
        ]);

        $res->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonCount(1, 'unavailable')
            ->assertJsonPath('items.0.product_id', $good->id);
    }

    // ── Campaigns are bundle promotions — products stay visible always ─────

    public function test_product_detail_visible_even_when_in_campaign(): void
    {
        $p = $this->activeProduct();
        $campaign = Campaign::create([
            'name' => 'Running',
            'slug' => 'running',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);
        $campaign->products()->attach($p->id);

        $this->getJson("/api/products/{$p->slug}")
            ->assertOk()
            ->assertJsonPath('slug', $p->slug);
    }

    public function test_product_listing_always_includes_product_regardless_of_campaign(): void
    {
        $normal = $this->activeProduct(['name' => 'Normal']);
        $inCampaign = $this->activeProduct(['name' => 'Bundle Member']);
        $expired = $this->activeProduct(['name' => 'Past Campaign Member']);

        Campaign::create([
            'name' => 'Live', 'slug' => 'live', 'is_active' => true,
            'start_at' => now()->subDay(), 'end_at' => now()->addDay(),
        ])->products()->attach($inCampaign->id);

        Campaign::create([
            'name' => 'Ended', 'slug' => 'ended-c', 'is_active' => true,
            'start_at' => now()->subDays(5), 'end_at' => now()->subDay(),
        ])->products()->attach($expired->id);

        $slugs = collect($this->getJson('/api/products')->json())->pluck('slug')->toArray();

        $this->assertContains($normal->slug, $slugs);
        $this->assertContains($inCampaign->slug, $slugs);
        $this->assertContains($expired->slug, $slugs);
    }

    public function test_campaign_show_404_when_not_running(): void
    {
        Campaign::create([
            'name' => 'Past',
            'slug' => 'past',
            'is_active' => true,
            'start_at' => now()->subDays(5),
            'end_at' => now()->subDay(),
        ]);
        $this->getJson('/api/campaigns/past')->assertNotFound();

        Campaign::create([
            'name' => 'Future',
            'slug' => 'future',
            'is_active' => true,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(7),
        ]);
        $this->getJson('/api/campaigns/future')->assertNotFound();
    }
}
