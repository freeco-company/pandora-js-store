<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\CustomerMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDedupeTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $i = 1): Product
    {
        return Product::create([
            'name' => "P$i", 'slug' => "p-$i", 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
        ]);
    }

    public function test_merge_reparents_orders_and_addresses(): void
    {
        $surviving = Customer::create([
            'name' => 'Real', 'email' => 'real@example.com', 'phone' => '0911000001',
            'password' => bcrypt('x'), 'total_orders' => 1, 'total_spent' => 1000,
        ]);
        $absorbed = Customer::create([
            'name' => 'Real', 'email' => 'Uxxx@line.user', 'line_id' => 'Uxxx',
            'phone' => '0911000001', 'password' => bcrypt('x'),
            'total_orders' => 2, 'total_spent' => 2500,
        ]);

        // 各自建一張訂單 + 地址
        $product = $this->makeProduct();
        Order::create([
            'order_number' => 'OS1', 'customer_id' => $surviving->id, 'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000, 'payment_method' => 'cod', 'shipping_method' => 'home_delivery',
        ]);
        Order::create([
            'order_number' => 'OA1', 'customer_id' => $absorbed->id, 'pricing_tier' => 'regular',
            'subtotal' => 2500, 'total' => 2500, 'payment_method' => 'cod', 'shipping_method' => 'home_delivery',
        ]);
        CustomerAddress::create([
            'customer_id' => $absorbed->id, 'recipient_name' => 'A',
            'phone' => '0911000001', 'street' => '台北市信義區',
        ]);

        $merger = app(CustomerMergeService::class);
        $stats = $merger->merge($surviving, $absorbed, 'test:manual');

        $this->assertSame(1, $stats['orders']); // absorbed 那邊的 1 張被 reparent
        $this->assertSame(1, $stats['customer_addresses']);

        // surviving 留下、absorbed 砍掉
        $this->assertNotNull(Customer::find($surviving->id));
        $this->assertNull(Customer::find($absorbed->id));

        // 訂單全到 surviving 名下
        $this->assertSame(2, Order::where('customer_id', $surviving->id)->count());

        // 累計欄位合併
        $surviving->refresh();
        $this->assertSame(3, (int) $surviving->total_orders);
        $this->assertSame(3500.0, (float) $surviving->total_spent);

        // identity 帶過來：line_id 補到 surviving
        $this->assertSame('Uxxx', $surviving->line_id);

        // merge log 寫入
        $this->assertSame(1, \DB::table('customer_merge_log')->count());
    }

    public function test_merge_resolves_unique_conflicts_in_wishlists(): void
    {
        $surviving = Customer::create([
            'name' => 'S', 'email' => 's@example.com', 'phone' => '0911000002', 'password' => bcrypt('x'),
        ]);
        $absorbed = Customer::create([
            'name' => 'A', 'email' => 'a-line@line.user', 'line_id' => 'Uabs', 'phone' => '0911000002',
            'password' => bcrypt('x'),
        ]);
        $product = $this->makeProduct();

        // 兩邊收藏同一個 product → (customer_id, product_id) unique 衝突
        Wishlist::create(['customer_id' => $surviving->id, 'product_id' => $product->id]);
        Wishlist::create(['customer_id' => $absorbed->id, 'product_id' => $product->id]);

        $merger = app(CustomerMergeService::class);
        $merger->merge($surviving, $absorbed, 'test:wishlist');

        // 不應有 duplicate row
        $this->assertSame(1, Wishlist::where('customer_id', $surviving->id)->count());
        $this->assertSame(0, Wishlist::where('customer_id', $absorbed->id ?? -1)->count());
    }

    public function test_pickSurvivor_prefers_real_email_over_placeholder(): void
    {
        $a = Customer::create([
            'name' => 'A', 'email' => 'real@example.com', 'phone' => '0911', 'password' => bcrypt('x'),
            'total_orders' => 0,
        ]);
        $b = Customer::create([
            'name' => 'B', 'email' => 'Uxxx@line.user', 'line_id' => 'Uxxx', 'phone' => '0922', 'password' => bcrypt('x'),
            'total_orders' => 5, // 即使 b 訂單較多也應輸給 a 的真實 email
        ]);

        $merger = app(CustomerMergeService::class);
        $picked = $merger->pickSurvivor($a, $b);

        $this->assertSame($a->id, $picked['surviving']->id);
        $this->assertSame($b->id, $picked['absorbed']->id);
    }

    public function test_pickSurvivor_falls_back_to_total_orders_when_emails_equal_kind(): void
    {
        $a = Customer::create([
            'name' => 'A', 'email' => 'a@example.com', 'phone' => '0911', 'password' => bcrypt('x'),
            'total_orders' => 1,
        ]);
        $b = Customer::create([
            'name' => 'B', 'email' => 'b@example.com', 'phone' => '0922', 'password' => bcrypt('x'),
            'total_orders' => 5,
        ]);

        $merger = app(CustomerMergeService::class);
        $picked = $merger->pickSurvivor($a, $b);

        $this->assertSame($b->id, $picked['surviving']->id);
    }

    public function test_detectDuplicates_finds_high_confidence_pair(): void
    {
        Customer::create([
            'name' => 'Real', 'email' => 'real@example.com', 'phone' => '0911999000', 'password' => bcrypt('x'),
        ]);
        Customer::create([
            'name' => 'Real', 'email' => 'Uxxx@line.user', 'line_id' => 'Uxxx', 'phone' => '0911999000', 'password' => bcrypt('x'),
        ]);
        Customer::create([
            'name' => 'Unrelated', 'email' => 'u@example.com', 'phone' => '0922888000', 'password' => bcrypt('x'),
        ]);

        $merger = app(CustomerMergeService::class);
        $candidates = $merger->detectDuplicates();

        $this->assertCount(1, $candidates);
        $this->assertSame('high', $candidates[0]['confidence']);
    }

    public function test_detectDuplicates_skips_dismissed_pair(): void
    {
        $a = Customer::create([
            'name' => 'X', 'email' => 'x@example.com', 'phone' => '0933', 'password' => bcrypt('x'),
        ]);
        $b = Customer::create([
            'name' => 'Y', 'email' => 'Uy@line.user', 'line_id' => 'Uy', 'phone' => '0933', 'password' => bcrypt('x'),
        ]);

        // 標記為「不是同一人」
        \DB::table('customer_merge_dismissed')->insert([
            'customer_a_id' => min($a->id, $b->id),
            'customer_b_id' => max($a->id, $b->id),
            'reason' => 'admin: 不是同一人',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $merger = app(CustomerMergeService::class);
        $candidates = $merger->detectDuplicates();

        $this->assertCount(0, $candidates);
    }

    public function test_command_auto_high_merges(): void
    {
        Customer::create([
            'name' => 'A', 'email' => 'a@example.com', 'phone' => '0944111000', 'password' => bcrypt('x'),
        ]);
        Customer::create([
            'name' => 'A', 'email' => 'Uauto@line.user', 'line_id' => 'Uauto', 'phone' => '0944111000', 'password' => bcrypt('x'),
        ]);

        $this->assertSame(2, Customer::count());

        $this->artisan('customers:dedupe', ['--auto-high' => true])
            ->assertSuccessful();

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, \DB::table('customer_merge_log')->count());
    }

    public function test_merge_preserves_referral_chain(): void
    {
        $referrer = Customer::create([
            'name' => 'R', 'email' => 'r@example.com', 'phone' => '0955', 'password' => bcrypt('x'),
        ]);
        $survive = Customer::create([
            'name' => 'S', 'email' => 's@example.com', 'phone' => '0966',
            'referred_by_customer_id' => $referrer->id, 'password' => bcrypt('x'),
        ]);
        $absorb = Customer::create([
            'name' => 'A', 'email' => 'Uabs@line.user', 'line_id' => 'Uabs', 'phone' => '0966',
            'password' => bcrypt('x'),
        ]);

        // 還有一個被 absorb 推薦的客戶 → merge 後應改為被 survive 推薦
        $referredByAbsorb = Customer::create([
            'name' => 'X', 'email' => 'x@example.com', 'phone' => '0977',
            'referred_by_customer_id' => $absorb->id, 'password' => bcrypt('x'),
        ]);

        app(CustomerMergeService::class)->merge($survive, $absorb, 'test:ref');

        $this->assertSame($survive->id, $referredByAbsorb->fresh()->referred_by_customer_id);
        // 原本的 survive ↔ referrer 鏈不動
        $this->assertSame($referrer->id, $survive->fresh()->referred_by_customer_id);
    }
}
