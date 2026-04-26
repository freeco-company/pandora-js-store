<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-test the Filament admin list pages so 500s from malformed closures
 * (e.g. fn (string $s) — Filament injects by param name, $s is unresolvable)
 * get caught in CI instead of on production.
 *
 * Each test seeds one record — enough to force every column's closure to run.
 */
class FilamentAdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@freeco.cc',
            'password' => bcrypt('secret'),
        ]);
    }

    public function test_campaigns_index_renders_with_records(): void
    {
        Campaign::create([
            'name' => 'Running',
            'slug' => 'r',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);
        Campaign::create([
            'name' => 'Ended',
            'slug' => 'e',
            'is_active' => true,
            'start_at' => now()->subDays(5),
            'end_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/campaigns')
            ->assertSuccessful();
    }

    public function test_products_index_renders(): void
    {
        Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800,
            'is_active' => true, 'stock_status' => 'instock',
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/products')
            ->assertSuccessful();
    }

    public function test_orders_index_renders(): void
    {
        $customer = Customer::create(['name' => 'C', 'email' => 'c@t.com', 'password' => bcrypt('x')]);
        $product = Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800,
            'is_active' => true, 'stock_status' => 'instock',
        ]);
        $order = Order::create([
            'order_number' => 'PDTEST01',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'shipping_fee' => 0, 'discount' => 0, 'total' => 1000,
            'payment_method' => 'ecpay_credit',
            'payment_status' => 'unpaid',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'X', 'shipping_phone' => '0900000000',
            'shipping_address' => '台北市',
        ]);
        // Seed one item so the items_count withCount path + "商品" modal
        // action both exercise their real render code.
        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'P',
            'quantity' => 2,
            'unit_price' => 1000,
            'subtotal' => 2000,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/orders')
            ->assertSuccessful();
    }

    public function test_customers_index_renders(): void
    {
        Customer::create(['name' => 'C', 'email' => 'c@t.com', 'password' => bcrypt('x')]);

        $this->actingAs($this->admin())
            ->get('/admin/customers')
            ->assertSuccessful();
    }

    public function test_visits_index_renders_empty(): void
    {
        // Page must render even when there are no rows — common state on day 1.
        $this->actingAs($this->admin())
            ->get('/admin/visits')
            ->assertSuccessful();
    }

    public function test_dashboard_renders_with_all_widgets(): void
    {
        // Smoke-test the dashboard page including the new VisitStatsWidget
        // and VisitTrendChart. Seed one of each relevant row so widgets
        // hit real data paths (not just the no-data shortcut).
        Visit::create([
            'visitor_id' => 'dashboard-seed',
            'ip' => '1.1.1.1',
            'device_type' => 'mobile',
            'os' => 'iOS',
            'browser' => 'Safari',
            'referer_source' => 'direct',
            'path' => '/',
            'visited_at' => now(),
        ]);
        Visit::create([
            'visitor_id' => 'dashboard-paid',
            'ip' => '2.2.2.2',
            'device_type' => 'desktop',
            'os' => 'Windows',
            'browser' => 'Chrome',
            'referer_source' => 'google_ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'launch',
            'path' => '/products',
            'visited_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_visits_index_renders_with_records(): void
    {
        // Seed one row per distinct referer_source so every branch of the
        // badge color/label match arm executes. Regression guard: a missing
        // arm previously crashed the whole page with
        // "Call to newQueryWithoutRelationships() on null".
        $sources = ['direct', 'google', 'google_ads', 'bing', 'yahoo', 'facebook', 'instagram', 'line', 'email', 'other', null];
        $customer = Customer::create(['name' => 'C', 'email' => 'c@t.com', 'password' => bcrypt('x')]);

        foreach ($sources as $i => $src) {
            Visit::create([
                'visitor_id' => 'test-visitor-' . $i,
                'session_id' => 'sess-' . $i,
                'ip' => '1.2.3.4',
                'country' => 'TW',
                'user_agent' => 'Mozilla/5.0',
                'device_type' => ['mobile', 'tablet', 'desktop'][$i % 3],
                'os' => 'iOS',
                'os_version' => '18.5',
                'browser' => 'Safari',
                'browser_version' => '18.5',
                'referer_source' => $src,
                'path' => '/products',
                'landing_path' => '/',
                'customer_id' => $i % 3 === 0 ? $customer->id : null,
                'visited_at' => now(),
            ]);
        }

        $this->actingAs($this->admin())
            ->get('/admin/visits')
            ->assertSuccessful();
    }

    public function test_reviews_index_renders(): void
    {
        $customer = Customer::create(['name' => 'C', 'email' => 'c@t.com', 'password' => bcrypt('x')]);
        $product = Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800,
            'is_active' => true, 'stock_status' => 'instock',
        ]);
        Review::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'reviewer_name' => 'X*',
            'is_visible' => true,
            'is_verified_purchase' => false,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/reviews')
            ->assertSuccessful();
    }

    public function test_customer_merger_renders_with_no_duplicates(): void
    {
        Customer::create([
            'name' => 'Solo', 'email' => 'solo@example.com', 'phone' => '0911000000',
            'password' => bcrypt('x'),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/customer-merger')
            ->assertSuccessful()
            ->assertSeeText('沒有偵測到重複會員');
    }

    public function test_customer_merger_renders_with_high_confidence_pair(): void
    {
        Customer::create([
            'name' => 'Real', 'email' => 'real@example.com', 'phone' => '0911999999',
            'password' => bcrypt('x'),
        ]);
        Customer::create([
            'name' => 'Real', 'email' => 'Uxxx@line.user', 'line_id' => 'Uxxx',
            'phone' => '0911999999', 'password' => bcrypt('x'),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/customer-merger')
            ->assertSuccessful()
            ->assertSeeText('信心：高')
            ->assertSeeText('LINE placeholder');
    }
}
