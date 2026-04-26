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

        // `created_at` 不是 fillable，要 raw update 才能模擬「N 小時前下的單」
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

    public function test_bank_transfer_unpaid_order_past_24h_gets_reminder(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subHours(25)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $order->refresh();
        $this->assertNotNull($order->confirmation_reminder_sent_at);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNotSame('cancelled', $order->status);
    }

    public function test_bank_transfer_unpaid_order_past_48h_gets_cancelled_and_stock_restored(): void
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
        $this->assertNull($order->fresh()->confirmation_reminder_sent_at);
    }

    public function test_bank_transfer_reminder_is_idempotent(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subHours(25)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();
        $firstSentAt = $order->fresh()->confirmation_reminder_sent_at;
        $this->assertNotNull($firstSentAt);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();
        $this->assertEquals($firstSentAt, $order->fresh()->confirmation_reminder_sent_at);
    }

    public function test_bank_transfer_below_24h_no_reminder(): void
    {
        $order = $this->makeOrder(['created_at' => now()->subHours(10)]);

        $this->artisan('bank-transfer:auto-cancel')->assertSuccessful();

        $this->assertNull($order->fresh()->confirmation_reminder_sent_at);
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

    public function test_cod_reminder_at_24h_no_cancel(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(25),
            'payment_method' => 'cod',
            'status' => 'pending_confirmation',
            'confirmation_token' => bin2hex(random_bytes(16)),
        ]);

        $this->artisan('cod:auto-cancel-unconfirmed')->assertSuccessful();

        $order->refresh();
        $this->assertNotNull($order->confirmation_reminder_sent_at);
        $this->assertSame('pending_confirmation', $order->status);
    }

    public function test_cod_cancel_at_48h(): void
    {
        $order = $this->makeOrder([
            'created_at' => now()->subHours(50),
            'payment_method' => 'cod',
            'status' => 'pending_confirmation',
            'confirmation_token' => bin2hex(random_bytes(16)),
            'confirmation_reminder_sent_at' => now()->subHours(25),
        ]);

        $this->artisan('cod:auto-cancel-unconfirmed')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
