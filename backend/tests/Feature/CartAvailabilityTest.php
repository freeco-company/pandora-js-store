<?php

namespace Tests\Feature;

use App\Models\Bundle;
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

    // ── Campaigns / Bundles — products stay visible always ─────

    private function makeRunningCampaign(array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'name' => 'Running',
            'slug' => 'running-' . uniqid(),
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ], $overrides));
    }

    private function makeBundle(Campaign $c, array $overrides = []): Bundle
    {
        return Bundle::create(array_merge([
            'campaign_id' => $c->id,
            'name' => $c->name . ' Bundle',
            'slug' => 'b-' . uniqid(),
            'sort_order' => 0,
        ], $overrides));
    }

    public function test_product_detail_visible_even_when_in_bundle(): void
    {
        $p = $this->activeProduct();
        $c = $this->makeRunningCampaign();
        $b = $this->makeBundle($c);
        $b->products()->attach($p->id, ['role' => 'buy', 'quantity' => 1]);

        $this->getJson("/api/products/{$p->slug}")
            ->assertOk()
            ->assertJsonPath('slug', $p->slug);
    }

    public function test_product_listing_always_includes_product_regardless_of_bundle(): void
    {
        $normal = $this->activeProduct(['name' => 'Normal']);
        $inBundle = $this->activeProduct(['name' => 'Bundle Member']);
        $expired = $this->activeProduct(['name' => 'Past Bundle Member']);

        $live = $this->makeBundle($this->makeRunningCampaign());
        $live->products()->attach($inBundle->id, ['role' => 'buy', 'quantity' => 1]);

        $endedCamp = Campaign::create([
            'name' => 'Ended', 'slug' => 'ended-' . uniqid(), 'is_active' => true,
            'start_at' => now()->subDays(5), 'end_at' => now()->subDay(),
        ]);
        $endedBundle = $this->makeBundle($endedCamp);
        $endedBundle->products()->attach($expired->id, ['role' => 'buy', 'quantity' => 1]);

        $slugs = collect($this->getJson('/api/products')->json())->pluck('slug')->toArray();

        $this->assertContains($normal->slug, $slugs);
        $this->assertContains($inBundle->slug, $slugs);
        $this->assertContains($expired->slug, $slugs);
    }

    // ── Bundle promotion model ─────────────────────────────────

    public function test_campaign_show_returns_nested_bundles(): void
    {
        $probio = $this->activeProduct(['name' => '益生菌', 'price' => 1000, 'vip_price' => 800]);
        $c = Campaign::create([
            'name' => '母親節',
            'slug' => 'mothers-day',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);
        $b = Bundle::create([
            'campaign_id' => $c->id,
            'name' => '益生菌買3送1',
            'slug' => 'mothers-day-probio',
        ]);
        $b->products()->attach($probio->id, ['role' => 'buy', 'quantity' => 3]);
        $b->products()->attach($probio->id, ['role' => 'gift', 'quantity' => 1]);

        $res = $this->getJson('/api/campaigns/mothers-day');
        $res->assertOk()
            ->assertJsonPath('name', '母親節')
            ->assertJsonCount(1, 'bundles')
            ->assertJsonPath('bundles.0.name', '益生菌買3送1')
            ->assertJsonPath('bundles.0.bundle_price', 2400)
            ->assertJsonPath('bundles.0.bundle_value_price', 3000)
            ->assertJsonCount(1, 'bundles.0.buy_items')
            ->assertJsonCount(1, 'bundles.0.gift_items');
    }

    public function test_bundle_show_returns_bundle_with_campaign_context(): void
    {
        $probio = $this->activeProduct(['name' => '益生菌', 'price' => 1000, 'vip_price' => 800]);
        $c = $this->makeRunningCampaign(['name' => '春季促銷', 'slug' => 'spring']);
        $b = Bundle::create([
            'campaign_id' => $c->id,
            'name' => '益生菌套組',
            'slug' => 'spring-probio-bundle',
        ]);
        $b->products()->attach($probio->id, ['role' => 'buy', 'quantity' => 3]);

        $res = $this->getJson('/api/bundles/spring-probio-bundle');
        $res->assertOk()
            ->assertJsonPath('name', '益生菌套組')
            ->assertJsonPath('bundle_price', 2400)
            ->assertJsonPath('campaign.slug', 'spring');
    }

    public function test_bundle_show_404_when_campaign_not_running(): void
    {
        $c = Campaign::create([
            'name' => 'Past', 'slug' => 'past-c', 'is_active' => true,
            'start_at' => now()->subDays(5), 'end_at' => now()->subDay(),
        ]);
        Bundle::create([
            'campaign_id' => $c->id, 'name' => 'Expired Bundle', 'slug' => 'expired-bundle',
        ]);

        $this->getJson('/api/bundles/expired-bundle')->assertNotFound();
    }

    public function test_cart_bundle_pricing_triggers_vip_for_whole_cart(): void
    {
        $probio = $this->activeProduct(['name' => '益生菌', 'price' => 1000, 'combo_price' => 900, 'vip_price' => 800]);
        $other = $this->activeProduct(['name' => 'Other', 'price' => 500, 'combo_price' => 450, 'vip_price' => 400]);

        $b = $this->makeBundle($this->makeRunningCampaign());
        $b->products()->attach($probio->id, ['role' => 'buy', 'quantity' => 3]);
        $b->products()->attach($probio->id, ['role' => 'gift', 'quantity' => 1]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [
                ['type' => 'bundle', 'bundle_id' => $b->id, 'quantity' => 1],
                ['product_id' => $other->id, 'quantity' => 1],
            ],
        ]);

        $res->assertOk()
            ->assertJsonPath('tier', 'vip')
            ->assertJsonPath('campaign_vip', true)
            ->assertJsonPath('total', 2800)
            ->assertJsonCount(1, 'bundles')
            ->assertJsonCount(1, 'items');
    }

    public function test_cart_rejects_expired_bundle(): void
    {
        $probio = $this->activeProduct(['price' => 1000, 'vip_price' => 800]);
        $endedCamp = Campaign::create([
            'name' => 'Done', 'slug' => 'done-' . uniqid(), 'is_active' => true,
            'start_at' => now()->subDays(5), 'end_at' => now()->subHour(),
        ]);
        $b = $this->makeBundle($endedCamp);
        $b->products()->attach($probio->id, ['role' => 'buy', 'quantity' => 3]);

        $res = $this->postJson('/api/cart/calculate', [
            'items' => [['type' => 'bundle', 'bundle_id' => $b->id, 'quantity' => 1]],
        ]);

        $res->assertOk()
            ->assertJsonPath('unavailable.0.reason', 'bundle_expired')
            ->assertJsonCount(0, 'bundles');
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
