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
            'phone' => '0912345678', 'password' => bcrypt('x'),
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

    public function test_authenticated_user_with_different_email_at_checkout_does_not_create_new_customer(): void
    {
        // 登入用戶在結帳填了不同 email — 應該複用認證帳號，不要建第二個 customer。
        $existing = Customer::create([
            'name' => 'Logged In', 'email' => 'logged@example.com',
            'phone' => '0911111111', 'password' => bcrypt('x'),
        ]);
        $token = $existing->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', $this->payload([
                'customer' => [
                    'name' => 'Logged In',
                    'email' => 'totally-different@example.com', // 不同 email
                    'phone' => '0911111111',
                ],
            ]))
            ->assertCreated();

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame($existing->id, Order::first()->customer_id);
        // 認證帳號的 email 不能被結帳表單蓋掉
        $this->assertSame('logged@example.com', $existing->fresh()->email);
    }

    public function test_authenticated_line_placeholder_user_gets_email_upgraded_at_checkout(): void
    {
        // LINE 登入用戶（email 是 placeholder），結帳填真實 email → 應升級
        $existing = Customer::create([
            'name' => 'LINE User', 'email' => 'Uxxx@line.user',
            'line_id' => 'Uxxx', 'phone' => null, 'password' => bcrypt('x'),
        ]);
        $token = $existing->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', $this->payload([
                'customer' => [
                    'name' => 'LINE User',
                    'email' => 'real@example.com',
                    'phone' => '0922222222',
                ],
            ]))
            ->assertCreated();

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame('real@example.com', $existing->fresh()->email);
    }

    public function test_guest_checkout_with_phone_match_to_line_placeholder_merges_into_existing(): void
    {
        // 同一人先用 LINE 登入過（phone 是後來補的）→ 後用訪客結帳填真實 email + 同 phone
        // 應認定為同一人，升級 placeholder email、複用該 customer。
        $existing = Customer::create([
            'name' => 'LINE Old', 'email' => 'Uyyy@line.user',
            'line_id' => 'Uyyy', 'phone' => '0933333333', 'password' => bcrypt('x'),
        ]);

        $this->postJson('/api/orders', $this->payload([
            'customer' => [
                'name' => 'LINE Old',
                'email' => 'real-from-checkout@example.com',
                'phone' => '0933333333',
            ],
        ]))->assertCreated();

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame('real-from-checkout@example.com', $existing->fresh()->email);
        $this->assertSame($existing->id, Order::first()->customer_id);
    }

    public function test_guest_checkout_phone_match_skipped_when_target_email_taken(): void
    {
        // phone 比對到 placeholder customer，但 typed email 已被另一 row 佔用 →
        // 不能升級（會炸 unique），改建新 customer。
        Customer::create([
            'name' => 'LINE Old', 'email' => 'Uzzz@line.user',
            'line_id' => 'Uzzz', 'phone' => '0944444444', 'password' => bcrypt('x'),
        ]);
        Customer::create([
            'name' => 'Email Owner', 'email' => 'taken@example.com',
            'phone' => null, 'password' => bcrypt('x'),
        ]);

        $this->postJson('/api/orders', $this->payload([
            'customer' => [
                'name' => 'Some Buyer',
                'email' => 'taken@example.com',
                'phone' => '0944444444',
            ],
        ]))->assertCreated();

        // taken@example.com 已存在 → 走「比對 email」路徑命中 Email Owner，不會新建
        $this->assertDatabaseCount('customers', 2);
        $emailOwner = Customer::where('email', 'taken@example.com')->first();
        $this->assertSame($emailOwner->id, Order::first()->customer_id);
        // placeholder 那邊不能被偷改
        $this->assertSame('Uzzz@line.user', Customer::where('line_id', 'Uzzz')->first()->email);
    }

    public function test_guest_checkout_phone_match_skipped_when_existing_has_real_email(): void
    {
        // phone 相同但對方 email 是真實 email（不是 placeholder）→ 認定為不同人
        // （家人共用電話、員工代下單等情境），不合併。
        Customer::create([
            'name' => 'Phone Sharer A', 'email' => 'a@example.com',
            'phone' => '0955555555', 'password' => bcrypt('x'),
        ]);

        $this->postJson('/api/orders', $this->payload([
            'customer' => [
                'name' => 'Phone Sharer B',
                'email' => 'b@example.com',
                'phone' => '0955555555',
            ],
        ]))->assertCreated();

        $this->assertDatabaseCount('customers', 2);
        $b = Customer::where('email', 'b@example.com')->first();
        $this->assertNotNull($b);
        $this->assertSame($b->id, Order::first()->customer_id);
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
