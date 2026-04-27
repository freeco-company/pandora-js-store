<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Services\AchievementCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewAchievementTest extends TestCase
{
    use RefreshDatabase;

    private function setupCustomerAndOrder(): array
    {
        $customer = Customer::create([
            'name' => 'R', 'email' => 'r@example.com',
            'phone' => '0911000000', 'password' => bcrypt('x'),
        ]);
        $product = Product::create([
            'name' => 'P', 'slug' => 'p-' . uniqid(), 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
            'stock_status' => 'instock', 'stock_quantity' => 10,
        ]);
        $order = Order::create([
            'order_number' => 'PD' . strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'status' => 'completed',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'shipping_method' => 'home_delivery',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'P',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
        ]);
        return [$customer, $product, $order];
    }

    private function postReview(Customer $customer, Product $product, Order $order, string $content = '', int $rating = 5): \Illuminate\Testing\TestResponse
    {
        $token = $customer->createToken('t')->plainTextToken;
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/reviews', [
                'product_id' => $product->id,
                'order_id' => $order->id,
                'rating' => $rating,
                'content' => $content,
            ]);
    }

    private function makeAdditionalReviewable(Customer $customer): array
    {
        $product = Product::create([
            'name' => 'P', 'slug' => 'p-' . uniqid(), 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
            'stock_status' => 'instock', 'stock_quantity' => 10,
        ]);
        $order = Order::create([
            'order_number' => 'PD' . strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'status' => 'completed',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'shipping_method' => 'home_delivery',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'P',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
        ]);
        return [$product, $order];
    }

    public function test_first_review_awards_first_review_achievement(): void
    {
        [$customer, $product, $order] = $this->setupCustomerAndOrder();

        $res = $this->postReview($customer, $product, $order, 'good');
        $res->assertCreated();

        $this->assertTrue(
            Achievement::where('customer_id', $customer->id)->where('code', AchievementCatalog::FIRST_REVIEW)->exists()
        );
        $this->assertContains(AchievementCatalog::FIRST_REVIEW, $res->json('_achievements'));
    }

    public function test_short_review_does_not_award_quality_review(): void
    {
        [$customer, $product, $order] = $this->setupCustomerAndOrder();

        $this->postReview($customer, $product, $order, 'OK')->assertCreated();

        $this->assertFalse(
            Achievement::where('customer_id', $customer->id)->where('code', AchievementCatalog::QUALITY_REVIEW)->exists()
        );
    }

    public function test_long_review_awards_quality_review(): void
    {
        [$customer, $product, $order] = $this->setupCustomerAndOrder();

        $longContent = '這個產品我用了一個月，效果非常好，很推薦給想要保健的朋友們。'; // > 30 chars
        $res = $this->postReview($customer, $product, $order, $longContent);
        $res->assertCreated();

        $this->assertTrue(
            Achievement::where('customer_id', $customer->id)->where('code', AchievementCatalog::QUALITY_REVIEW)->exists()
        );
        $this->assertContains(AchievementCatalog::QUALITY_REVIEW, $res->json('_achievements'));
    }

    public function test_review_milestones_3_5_10(): void
    {
        [$customer, $firstProduct, $firstOrder] = $this->setupCustomerAndOrder();

        // 第 1 則
        $this->postReview($customer, $firstProduct, $firstOrder)->assertCreated();
        $this->assertSame(1, Achievement::where('customer_id', $customer->id)->whereIn('code', [
            AchievementCatalog::FIRST_REVIEW,
            AchievementCatalog::REVIEW_3, AchievementCatalog::REVIEW_5, AchievementCatalog::REVIEW_10,
        ])->count());

        // 第 2 則 — 仍只有 first_review
        [$p2, $o2] = $this->makeAdditionalReviewable($customer);
        $this->postReview($customer, $p2, $o2)->assertCreated();
        $this->assertFalse(Achievement::where('customer_id', $customer->id)->where('code', AchievementCatalog::REVIEW_3)->exists());

        // 第 3 則 → review_3
        [$p3, $o3] = $this->makeAdditionalReviewable($customer);
        $res3 = $this->postReview($customer, $p3, $o3);
        $res3->assertCreated();
        $this->assertContains(AchievementCatalog::REVIEW_3, $res3->json('_achievements'));

        // 第 4 則 — 不會再頒 review_3（idempotent）
        [$p4, $o4] = $this->makeAdditionalReviewable($customer);
        $this->postReview($customer, $p4, $o4)->assertCreated();

        // 第 5 則 → review_5
        [$p5, $o5] = $this->makeAdditionalReviewable($customer);
        $res5 = $this->postReview($customer, $p5, $o5);
        $res5->assertCreated();
        $this->assertContains(AchievementCatalog::REVIEW_5, $res5->json('_achievements'));

        // 第 10 則需要再 5 個訂單，跳過 4-9 直接 seed
        for ($i = 0; $i < 4; $i++) {
            [$p, $o] = $this->makeAdditionalReviewable($customer);
            $this->postReview($customer, $p, $o)->assertCreated();
        }
        [$p10, $o10] = $this->makeAdditionalReviewable($customer);
        $res10 = $this->postReview($customer, $p10, $o10);
        $res10->assertCreated();
        $this->assertContains(AchievementCatalog::REVIEW_10, $res10->json('_achievements'));
    }

    public function test_progress_calculator_returns_review_count(): void
    {
        [$customer, $product, $order] = $this->setupCustomerAndOrder();
        $this->postReview($customer, $product, $order, 'good review here')->assertCreated();

        $progress = app(\App\Services\AchievementProgressCalculator::class)->forCustomer($customer);

        $this->assertSame(1, $progress[AchievementCatalog::REVIEW_3]['current']);
        $this->assertSame(3, $progress[AchievementCatalog::REVIEW_3]['target']);
        $this->assertSame(1, $progress[AchievementCatalog::REVIEW_5]['current']);
        $this->assertSame(1, $progress[AchievementCatalog::REVIEW_10]['current']);
    }

    public function test_seeded_reviews_do_not_count_for_progress(): void
    {
        [$customer, $product, $order] = $this->setupCustomerAndOrder();

        // 種子評論不算
        Review::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'rating' => 5,
            'reviewer_name' => 'X*',
            'is_visible' => true,
            'is_verified_purchase' => false,
            'is_seeded' => true,
        ]);

        $progress = app(\App\Services\AchievementProgressCalculator::class)->forCustomer($customer);
        $this->assertSame(0, $progress[AchievementCatalog::REVIEW_3]['current']);
    }
}
