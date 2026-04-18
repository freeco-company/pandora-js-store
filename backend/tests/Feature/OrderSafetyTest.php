<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * P0: Order safety — stock deduction, idempotency, coupon locking,
 * deactivated product rejection, CVS validation, email normalization.
 */
class OrderSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function product(array $overrides = []): Product
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

    private function orderPayload(Product $product, array $override = []): array
    {
        return array_merge([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer' => [
                'name' => '測試',
                'email' => 'test@example.com',
                'phone' => '0912345678',
            ],
            'payment_method' => 'bank_transfer',
            'shipping_method' => 'home_delivery',
            'shipping_name' => '測試',
            'shipping_phone' => '0912345678',
            'shipping_address' => '台北市中山區',
        ], $override);
    }

    // ── P0-1: Deactivated product blocks order ─────────────────

    public function test_order_rejected_when_product_inactive(): void
    {
        $p = $this->product(['is_active' => false]);

        $this->postJson('/api/orders', $this->orderPayload($p))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '無法購買'));
    }

    // ── P0-2: Out-of-stock blocks order ────────────────────────

    public function test_order_rejected_when_product_out_of_stock(): void
    {
        $p = $this->product(['stock_status' => 'outofstock']);

        $this->postJson('/api/orders', $this->orderPayload($p))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '無法購買'));
    }

    // ── P0-3: Insufficient stock blocks order ──────────────────

    public function test_order_rejected_when_insufficient_stock(): void
    {
        $p = $this->product(['stock_quantity' => 2]);

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'items' => [['product_id' => $p->id, 'quantity' => 5]],
        ]))->assertStatus(422);
    }

    // ── P0-4: Stock deduction on order ─────────────────────────

    public function test_order_deducts_stock(): void
    {
        $p = $this->product(['stock_quantity' => 10]);

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'items' => [['product_id' => $p->id, 'quantity' => 3]],
        ]))->assertCreated();

        $this->assertSame(7, $p->fresh()->stock_quantity);
    }

    public function test_order_marks_product_out_of_stock_when_depleted(): void
    {
        $p = $this->product(['stock_quantity' => 2, 'stock_status' => 'instock']);

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'items' => [['product_id' => $p->id, 'quantity' => 2]],
        ]))->assertCreated();

        $fresh = $p->fresh();
        $this->assertSame(0, $fresh->stock_quantity);
        $this->assertSame('outofstock', $fresh->stock_status);
    }

    public function test_order_does_not_deduct_untracked_stock(): void
    {
        $p = $this->product(['stock_quantity' => 0]); // 0 = untracked

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'items' => [['product_id' => $p->id, 'quantity' => 5]],
        ]))->assertCreated();

        $this->assertSame(0, $p->fresh()->stock_quantity);
    }

    // ── P0-5: Idempotency — duplicate submit returns same order ─

    public function test_idempotency_key_prevents_duplicate_order(): void
    {
        $p = $this->product();
        $payload = $this->orderPayload($p, [
            'idempotency_key' => 'unique-key-123',
        ]);

        $res1 = $this->postJson('/api/orders', $payload);
        $res1->assertCreated();
        $orderNumber = $res1->json('order_number');

        // Second submit with same key — should return the existing order
        $res2 = $this->postJson('/api/orders', $payload);
        $res2->assertOk()
            ->assertJsonPath('order.order_number', $orderNumber);

        // Only one order should exist
        $this->assertDatabaseCount('orders', 1);
    }

    // ── P0-6: Coupon max_uses enforced ─────────────────────────

    public function test_coupon_max_uses_enforced(): void
    {
        $coupon = Coupon::create([
            'code' => 'ONCE', 'type' => 'fixed', 'value' => 100,
            'is_active' => true, 'max_uses' => 1,
        ]);

        $p = $this->product();

        // First use — should succeed
        $this->postJson('/api/orders', $this->orderPayload($p, [
            'coupon_code' => 'ONCE',
            'customer' => ['name' => 'A', 'email' => 'a@test.com', 'phone' => '0911111111'],
        ]))->assertCreated();

        // Second use — should fail
        $p2 = $this->product();
        $this->postJson('/api/orders', $this->orderPayload($p2, [
            'coupon_code' => 'ONCE',
            'customer' => ['name' => 'B', 'email' => 'b@test.com', 'phone' => '0922222222'],
        ]))->assertStatus(422);
    }

    // ── P1-2: CVS requires store_id and store_name ─────────────

    public function test_cvs_order_requires_store_fields(): void
    {
        $p = $this->product();

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'shipping_method' => 'cvs_711',
            // Missing shipping_store_id and shipping_store_name
        ]))->assertStatus(422);
    }

    public function test_cvs_order_succeeds_with_store_fields(): void
    {
        $p = $this->product();

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'shipping_method' => 'cvs_711',
            'shipping_store_id' => 'STORE001',
            'shipping_store_name' => '測試門市',
            'shipping_address' => null,
        ]))->assertCreated();
    }

    // ── P1-3: Email normalization ──────────────────────────────

    public function test_email_normalized_to_lowercase_and_trimmed(): void
    {
        $p = $this->product();

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'customer' => [
                'name' => 'Test',
                'email' => '  User@Example.COM  ',
                'phone' => '0912345678',
            ],
        ]))->assertCreated();

        $this->assertDatabaseHas('customers', ['email' => 'user@example.com']);
    }

    public function test_same_email_different_case_reuses_customer(): void
    {
        $p = $this->product();

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'customer' => ['name' => 'A', 'email' => 'same@test.com', 'phone' => '0912345678'],
        ]))->assertCreated();

        $p2 = $this->product();
        $this->postJson('/api/orders', $this->orderPayload($p2, [
            'customer' => ['name' => 'A', 'email' => 'SAME@Test.COM', 'phone' => '0912345678'],
        ]))->assertCreated();

        $this->assertDatabaseCount('customers', 1);
    }

    // ── P2: Phone validation ───────────────────────────────────

    public function test_phone_must_be_valid_taiwan_mobile(): void
    {
        $p = $this->product();

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'customer' => ['name' => 'X', 'email' => 'x@t.com', 'phone' => '12345'],
        ]))->assertStatus(422);

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'customer' => ['name' => 'X', 'email' => 'x@t.com', 'phone' => '0912345678'],
        ]))->assertCreated();
    }

    // ── P2: Quantity limit ─────────────────────────────────────

    public function test_quantity_capped_at_99(): void
    {
        $p = $this->product();

        $this->postJson('/api/orders', $this->orderPayload($p, [
            'items' => [['product_id' => $p->id, 'quantity' => 100]],
        ]))->assertStatus(422);
    }
}
