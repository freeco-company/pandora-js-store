<?php

namespace Tests\Feature;

use App\Models\Blacklist;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private ?Product $cachedProduct = null;

    private function product(): Product
    {
        return $this->cachedProduct ??= Product::create([
            'name' => 'Prod', 'slug' => 'prod', 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'items' => [['product_id' => $this->product()->id, 'quantity' => 1]],
            'customer' => [
                'name' => '測試使用者',
                'email' => 'buyer@example.com',
                'phone' => '0911222333',
            ],
            'payment_method' => 'bank_transfer',
            'shipping_method' => 'home_delivery',
            'shipping_name' => '測試使用者',
            'shipping_phone' => '0911222333',
            'shipping_address' => '台北市中山區中山北路',
        ], $override);
    }

    public function test_creates_order_with_unique_order_number(): void
    {
        $res = $this->postJson('/api/orders', $this->payload());

        $res->assertCreated()
            ->assertJsonPath('pricing_tier', 'regular')
            ->assertJsonPath('total', '1000.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertStringStartsWith('PD', $res->json('order_number'));
    }

    public function test_creates_customer_on_guest_checkout(): void
    {
        $this->postJson('/api/orders', $this->payload())->assertCreated();

        $this->assertDatabaseHas('customers', ['email' => 'buyer@example.com']);
    }

    public function test_reuses_existing_customer(): void
    {
        Customer::create([
            'name' => 'Existing', 'email' => 'buyer@example.com',
            'phone' => '000', 'password' => bcrypt('x'),
        ]);

        $this->postJson('/api/orders', $this->payload())->assertCreated();

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_blacklist_blocks_cod_but_not_other_methods(): void
    {
        Blacklist::create([
            'email' => 'buyer@example.com', 'is_active' => true,
            'reason' => '未取貨',
        ]);

        $this->postJson('/api/orders', $this->payload(['payment_method' => 'cod']))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '貨到付款'));

        $this->postJson('/api/orders', $this->payload(['payment_method' => 'bank_transfer']))
            ->assertCreated();
    }

    public function test_applies_valid_coupon(): void
    {
        Coupon::create(['code' => 'SAVE100', 'type' => 'fixed', 'value' => 100, 'is_active' => true]);

        $res = $this->postJson('/api/orders', $this->payload(['coupon_code' => 'SAVE100']));

        $res->assertCreated()
            ->assertJsonPath('discount', '100.00')
            ->assertJsonPath('total', '900.00');

        $this->assertSame(1, Coupon::where('code', 'SAVE100')->first()->used_count);
    }

    public function test_rejects_invalid_coupon(): void
    {
        $this->postJson('/api/orders', $this->payload(['coupon_code' => 'GHOST']))
            ->assertStatus(422);
    }

    public function test_validation_rejects_bad_payment_method(): void
    {
        $this->postJson('/api/orders', $this->payload(['payment_method' => 'bitcoin']))
            ->assertStatus(422);
    }

    public function test_sends_confirmation_email(): void
    {
        $this->postJson('/api/orders', $this->payload())->assertCreated();

        Mail::assertSent(\App\Mail\OrderConfirmation::class);
    }

    public function test_show_requires_matching_email(): void
    {
        $this->postJson('/api/orders', $this->payload())->assertCreated();
        $order = Order::first();

        $this->getJson("/api/orders/{$order->order_number}?email=buyer@example.com")
            ->assertOk()
            ->assertJsonPath('order_number', $order->order_number);

        $this->getJson("/api/orders/{$order->order_number}?email=wrong@example.com")
            ->assertNotFound();

        $this->getJson("/api/orders/{$order->order_number}")
            ->assertStatus(403);
    }

    public function test_check_cod_endpoint(): void
    {
        Blacklist::create(['email' => 'bad@example.com', 'is_active' => true, 'reason' => 'x']);

        $this->postJson('/api/orders/check-cod', ['email' => 'bad@example.com'])
            ->assertOk()
            ->assertJsonPath('cod_available', false);

        $this->postJson('/api/orders/check-cod', ['email' => 'good@example.com'])
            ->assertOk()
            ->assertJsonPath('cod_available', true);
    }
}
