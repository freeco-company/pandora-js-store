<?php

namespace Tests\Feature;

use App\Mail\ReviewReminderMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewReminderTest extends TestCase
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
            'name' => $attrs['product_name'] ?? 'Prod', 'slug' => 'p-' . uniqid(),
            'price' => 1000, 'combo_price' => 900, 'vip_price' => 800,
            'is_active' => true, 'stock_status' => 'instock', 'stock_quantity' => 10,
        ]);
        $customer = $attrs['customer'] ?? Customer::create([
            'name' => 'Buyer', 'email' => 'b' . uniqid() . '@example.com',
            'phone' => '0911' . random_int(100000, 999999), 'password' => bcrypt('x'),
        ]);
        unset($attrs['customer'], $attrs['product_name']);

        $updatedAt = $attrs['updated_at'] ?? null;
        unset($attrs['updated_at']);

        $order = Order::create(array_merge([
            'order_number' => 'PD' . strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'status' => 'completed',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'Buyer',
        ], $attrs));

        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
        ]);

        if ($updatedAt) {
            \DB::table('orders')->where('id', $order->id)->update(['updated_at' => $updatedAt]);
            $order->refresh();
        }
        return $order;
    }

    public function test_sends_first_reminder_at_7_days(): void
    {
        $order = $this->makeOrder(['updated_at' => now()->subDays(8)]);

        $this->artisan('review:remind')->assertSuccessful();

        $order->refresh();
        $this->assertNotNull($order->review_reminder_sent_at);
        $this->assertNull($order->review_reminder_2_sent_at);
        Mail::assertSent(ReviewReminderMail::class, function ($mail) {
            return $mail->isFinal === false;
        });
    }

    public function test_does_not_send_first_reminder_below_7_days(): void
    {
        $order = $this->makeOrder(['updated_at' => now()->subDays(3)]);

        $this->artisan('review:remind')->assertSuccessful();

        $this->assertNull($order->fresh()->review_reminder_sent_at);
        Mail::assertNothingSent();
    }

    public function test_first_reminder_idempotent(): void
    {
        $order = $this->makeOrder(['updated_at' => now()->subDays(8)]);

        $this->artisan('review:remind')->assertSuccessful();
        $firstAt = $order->fresh()->review_reminder_sent_at;
        $this->assertNotNull($firstAt);

        // 再跑：不會再寄第一輪（在 7d~14d 之間）
        $this->artisan('review:remind')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_skips_orders_with_all_products_already_reviewed(): void
    {
        $order = $this->makeOrder(['updated_at' => now()->subDays(8)]);
        $item = $order->items->first();
        Review::create([
            'product_id' => $item->product_id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'rating' => 5,
            'reviewer_name' => 'X*',
            'is_visible' => true,
            'is_verified_purchase' => true,
        ]);

        $this->artisan('review:remind')->assertSuccessful();

        // 全評過 → 標記但不寄
        $this->assertNotNull($order->fresh()->review_reminder_sent_at);
        Mail::assertNothingSent();
    }

    public function test_sends_final_reminder_at_14_days_after_first_sent(): void
    {
        $order = $this->makeOrder([
            'updated_at' => now()->subDays(15),
            'review_reminder_sent_at' => now()->subDays(8), // phase 1 已寄
        ]);

        $this->artisan('review:remind')->assertSuccessful();

        $order->refresh();
        $this->assertNotNull($order->review_reminder_2_sent_at);
        Mail::assertSent(ReviewReminderMail::class, function ($mail) {
            return $mail->isFinal === true;
        });
    }

    public function test_does_not_send_final_reminder_if_phase_1_never_sent(): void
    {
        // phase 1 沒寄過（review_reminder_sent_at = null）→ phase 2 不該獨立觸發
        // （這個情境發生在 reminder 第一次 schedule 時就抓到 14d 以上的舊單）
        $order = $this->makeOrder(['updated_at' => now()->subDays(20)]);

        $this->artisan('review:remind')->assertSuccessful();

        $order->refresh();
        // phase 1 條件命中（>7d）→ 寄第一輪
        $this->assertNotNull($order->review_reminder_sent_at);
        // phase 2 不會同 tick 跑（要求 review_reminder_sent_at 不為空，但這 tick 才寫）
        $this->assertNull($order->review_reminder_2_sent_at);
    }

    public function test_does_not_resend_after_both_phases(): void
    {
        $order = $this->makeOrder([
            'updated_at' => now()->subDays(20),
            'review_reminder_sent_at' => now()->subDays(13),
            'review_reminder_2_sent_at' => now()->subDays(6),
        ]);

        $this->artisan('review:remind')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_skips_stale_orders_older_than_60_days(): void
    {
        $order = $this->makeOrder(['updated_at' => now()->subDays(70)]);

        $this->artisan('review:remind')->assertSuccessful();

        $this->assertNull($order->fresh()->review_reminder_sent_at);
        Mail::assertNothingSent();
    }

    public function test_skips_non_completed_orders(): void
    {
        $order = $this->makeOrder([
            'updated_at' => now()->subDays(8),
            'status' => 'shipped', // 還沒 completed
        ]);

        $this->artisan('review:remind')->assertSuccessful();

        $this->assertNull($order->fresh()->review_reminder_sent_at);
        Mail::assertNothingSent();
    }

    public function test_skips_line_user_email_placeholder(): void
    {
        // LINE 登入沒給 email 的客人 — email 是 placeholder，但有 line_id 可推 LINE
        $customer = Customer::create([
            'name' => 'LineUser', 'email' => 'Uxxx@line.user', 'line_id' => 'Uxxx',
            'password' => bcrypt('x'),
        ]);
        $order = $this->makeOrder([
            'customer' => $customer,
            'updated_at' => now()->subDays(8),
        ]);

        $this->artisan('review:remind')->assertSuccessful();

        // email 沒寄（placeholder）但 LINE 推不可能 work（測試環境 LINE 沒 configured）
        // 沒 channel 設好 → notifyOrder 回 false → 不該標記
        // 但實務上有 LINE token 時就會推 → 標記成功
        Mail::assertNothingSent();
    }
}
