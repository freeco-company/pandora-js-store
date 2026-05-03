<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeOrder(array $attrs = []): Order
    {
        $product = Product::create([
            'name' => 'P', 'slug' => 'p-' . uniqid(), 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
            'stock_status' => 'instock', 'stock_quantity' => 10,
        ]);
        $customer = $attrs['customer'] ?? Customer::create([
            'name' => 'Buyer', 'email' => 'b' . uniqid() . '@example.com',
            'phone' => '0911' . random_int(100000, 999999),
            'password' => bcrypt('x'),
        ]);
        unset($attrs['customer']);

        $createdAt = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);

        $order = Order::create(array_merge([
            'order_number' => 'PD' . strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'status' => 'pending',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'shipping_fee' => 0, 'discount' => 0, 'total' => 1000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'B', 'shipping_phone' => '0911000000',
            'shipping_address' => '台北市',
        ], $attrs));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'P', 'quantity' => 2, 'unit_price' => 1000, 'subtotal' => 2000,
        ]);

        if ($createdAt) {
            \DB::table('orders')->where('id', $order->id)->update(['created_at' => $createdAt]);
            $order->refresh();
        }

        return $order;
    }

    // ── Bank transfer ───────────────────────────────────────────

    public function test_bank_transfer_at_3h_gets_stage_1_reminder(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subHours(4)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $order->refresh();
        $this->assertSame(1, (int) $order->confirmation_reminder_stage);
        $this->assertNotNull($order->confirmation_reminder_sent_at);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_bank_transfer_at_25h_gets_stage_2_reminder(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subHours(25)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $order->refresh();
        $this->assertSame(2, (int) $order->confirmation_reminder_stage);
        $this->assertNotNull($order->confirmation_reminder_sent_at);
        $this->assertNotSame('cancelled', $order->status);
    }

    public function test_bank_transfer_below_3h_no_reminder(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subHours(1)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $this->assertSame(0, (int) $order->fresh()->confirmation_reminder_stage);
        $this->assertNull($order->fresh()->confirmation_reminder_sent_at);
    }

    public function test_bank_transfer_cancel_at_48h(): void
    {
        $product = Product::create([
            'name' => 'BTP', 'slug' => 'btp', 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
            'stock_status' => 'instock', 'stock_quantity' => 8,
        ]);
        $customer = Customer::create(['name' => 'B', 'email' => 'bt@example.com', 'phone' => '0922', 'password' => bcrypt('x')]);
        $order = Order::create([
            'order_number' => 'PDBT001', 'customer_id' => $customer->id, 'status' => 'pending',
            'pricing_tier' => 'regular', 'subtotal' => 2000, 'total' => 2000,
            'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid',
            'shipping_method' => 'home_delivery',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'BTP',
            'quantity' => 2, 'unit_price' => 1000, 'subtotal' => 2000,
        ]);
        \DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subHours(50)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertStringContainsString('未完成銀行轉帳', $order->note);
        $this->assertSame(10, (int) $product->fresh()->stock_quantity);
    }

    public function test_bank_transfer_paid_order_is_not_cancelled(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(50),
            'payment_status' => 'paid',
        ]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $this->assertNotSame('cancelled', $order->fresh()->status);
    }

    public function test_bank_transfer_already_cancelled_is_not_touched(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(60),
            'status' => 'cancelled',
        ]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, (int) $order->fresh()->confirmation_reminder_stage);
    }

    public function test_bank_transfer_reminder_is_idempotent_within_same_stage(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subHours(4)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();
        $firstSentAt = $order->fresh()->confirmation_reminder_sent_at;
        $this->assertSame(1, (int) $order->fresh()->confirmation_reminder_stage);

        // 第二次跑（仍在 stage 1 區間）— 不應重送
        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();
        $this->assertEquals($firstSentAt, $order->fresh()->confirmation_reminder_sent_at);
        $this->assertSame(1, (int) $order->fresh()->confirmation_reminder_stage);
    }

    public function test_bank_transfer_cancellation_restores_coupon(): void
    {
        $coupon = Coupon::create(['code' => 'BTSAVE', 'type' => 'fixed', 'value' => 100, 'is_active' => true, 'used_count' => 5]);
        $order = $this->makeOrder([
            'created_at' => now()->subHours(50),
            'coupon_id' => $coupon->id,
        ]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $this->assertSame(4, (int) $coupon->fresh()->used_count);
    }

    // ── COD pending_confirmation ───────────────────────────────

    public function test_cod_at_3h_gets_stage_1_reminder(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(4),
            'payment_method' => 'cod',
            'status' => 'pending_confirmation',
            'confirmation_token' => bin2hex(random_bytes(16)),
        ]);

        $this->artisan('cod:auto-cancel-unconfirmed')->assertSuccessful();

        $order->refresh();
        $this->assertSame(1, (int) $order->confirmation_reminder_stage);
        $this->assertSame('pending_confirmation', $order->status);
    }

    public function test_cod_at_25h_gets_stage_2_reminder(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(25),
            'payment_method' => 'cod',
            'status' => 'pending_confirmation',
            'confirmation_token' => bin2hex(random_bytes(16)),
        ]);

        $this->artisan('cod:auto-cancel-unconfirmed')->assertSuccessful();

        $order->refresh();
        $this->assertSame(2, (int) $order->confirmation_reminder_stage);
        $this->assertSame('pending_confirmation', $order->status);
    }

    public function test_cod_cancel_at_48h(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(50),
            'payment_method' => 'cod',
            'status' => 'pending_confirmation',
            'confirmation_token' => bin2hex(random_bytes(16)),
        ]);

        $this->artisan('cod:auto-cancel-unconfirmed')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    // ── Credit card (ecpay_credit) ─────────────────────────────

    public function test_cc_at_1h_gets_stage_1_reminder(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(2),
            'payment_method' => 'ecpay_credit',
        ]);

        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame(1, (int) $order->confirmation_reminder_stage);
        $this->assertNotNull($order->confirmation_reminder_sent_at);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_cc_at_7h_gets_stage_2_reminder(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(7),
            'payment_method' => 'ecpay_credit',
        ]);

        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();

        $this->assertSame(2, (int) $order->fresh()->confirmation_reminder_stage);
    }

    public function test_cc_at_25h_gets_stage_3_reminder(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(25),
            'payment_method' => 'ecpay_credit',
        ]);

        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();

        $this->assertSame(3, (int) $order->fresh()->confirmation_reminder_stage);
    }

    public function test_cc_at_145h_gets_stage_5_reminder(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(145),
            'payment_method' => 'ecpay_credit',
        ]);

        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();

        $this->assertSame(5, (int) $order->fresh()->confirmation_reminder_stage);
    }

    public function test_cc_below_1h_no_reminder(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subMinutes(30),
            'payment_method' => 'ecpay_credit',
        ]);

        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();

        $this->assertSame(0, (int) $order->fresh()->confirmation_reminder_stage);
    }

    public function test_cc_cancel_at_7d(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(170),
            'payment_method' => 'ecpay_credit',
        ]);

        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertStringContainsString('未完成信用卡付款', $order->fresh()->note);
    }

    public function test_cc_paid_order_is_not_touched(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(170),
            'payment_method' => 'ecpay_credit',
            'payment_status' => 'paid',
        ]);

        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();

        $this->assertNotSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, (int) $order->fresh()->confirmation_reminder_stage);
    }

    public function test_cc_stage_progresses_across_runs(): void
    {
        // 起始 4h，跑一次 → stage 1
        $order = $this->makeOrder([
            'created_at' => now()->subHours(4),
            'payment_method' => 'ecpay_credit',
        ]);
        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();
        $this->assertSame(1, (int) $order->fresh()->confirmation_reminder_stage);

        // 推到 7h → 跑一次 → 應推進到 stage 2
        \DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subHours(7)]);
        $this->artisan('cc:auto-cancel-unpaid')->assertSuccessful();
        $this->assertSame(2, (int) $order->fresh()->confirmation_reminder_stage);
    }

}
