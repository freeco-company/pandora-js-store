<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
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
        Order::create([
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
}
