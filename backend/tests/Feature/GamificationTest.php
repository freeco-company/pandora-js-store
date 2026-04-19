<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AchievementCatalog;
use App\Services\AchievementService;
use App\Services\OrderAchievementEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_award_is_idempotent(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $svc = app(AchievementService::class);

        $this->assertSame(AchievementCatalog::FIRST_ORDER, $svc->award($customer, AchievementCatalog::FIRST_ORDER));
        $this->assertNull($svc->award($customer, AchievementCatalog::FIRST_ORDER));
        $this->assertSame(1, Achievement::where('customer_id', $customer->id)->count());
    }

    public function test_award_rejects_unknown_code(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $svc = app(AchievementService::class);

        $this->assertNull($svc->award($customer, 'ghost_code'));
        $this->assertSame(0, Achievement::count());
    }

    public function test_bump_streak_increments_daily_and_awards_at_7(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
            'streak_days' => 6,
            'last_active_date' => now()->subDay()->toDateString(),
        ]);
        $svc = app(AchievementService::class);

        $awarded = $svc->bumpStreak($customer);

        $this->assertSame(AchievementCatalog::STREAK_7, $awarded);
        $this->assertSame(7, $customer->fresh()->streak_days);
    }

    public function test_bump_streak_resets_on_gap(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
            'streak_days' => 5,
            'last_active_date' => now()->subDays(3)->toDateString(),
        ]);

        app(AchievementService::class)->bumpStreak($customer);

        $this->assertSame(1, $customer->fresh()->streak_days);
    }

    public function test_bump_streak_no_op_on_same_day(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
            'streak_days' => 3,
            'last_active_date' => now()->toDateString(),
        ]);

        $awarded = app(AchievementService::class)->bumpStreak($customer);

        $this->assertNull($awarded);
        $this->assertSame(3, $customer->fresh()->streak_days);
    }

    public function test_checkout_awards_first_order_and_returns_keys(): void
    {
        $product = Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
        ]);

        $res = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer' => ['name' => 'X', 'email' => 'x@e.com', 'phone' => '0912345678'],
            'payment_method' => 'cod',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'X', 'shipping_phone' => '0912345678',
            'shipping_address' => 'addr',
        ]);

        $res->assertCreated();
        $achievements = $res->json('_achievements');
        $this->assertContains(AchievementCatalog::FIRST_ORDER, $achievements);
    }

    public function test_checkout_awards_combo_tier_unlock(): void
    {
        $product = Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 1000,
            'combo_price' => 900, 'vip_price' => 800, 'is_active' => true,
        ]);

        $res = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'customer' => ['name' => 'X', 'email' => 'x@e.com', 'phone' => '0912345678'],
            'payment_method' => 'cod',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'X', 'shipping_phone' => '0912345678',
            'shipping_address' => 'addr',
        ]);

        $res->assertCreated();
        $this->assertContains(AchievementCatalog::UNLOCK_COMBO, $res->json('_achievements'));
    }

    public function test_checkout_awards_vip_tier_unlock(): void
    {
        $product = Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 2000,
            'combo_price' => 1800, 'vip_price' => 1600, 'is_active' => true,
        ]);

        $res = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'customer' => ['name' => 'X', 'email' => 'x@e.com', 'phone' => '0912345678'],
            'payment_method' => 'cod',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'X', 'shipping_phone' => '0912345678',
            'shipping_address' => 'addr',
        ]);

        $res->assertCreated();
        $this->assertContains(AchievementCatalog::UNLOCK_VIP, $res->json('_achievements'));
    }

    public function test_checkout_awards_spending_milestone(): void
    {
        $product = Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 1500,
            'combo_price' => 1500, 'vip_price' => 1500, 'is_active' => true,
        ]);

        $res = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer' => ['name' => 'X', 'email' => 'x@e.com', 'phone' => '0912345678'],
            'payment_method' => 'cod',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'X', 'shipping_phone' => '0912345678',
            'shipping_address' => 'addr',
        ]);

        $this->assertContains(AchievementCatalog::SPEND_1K, $res->json('_achievements'));
    }

    public function test_checkout_awards_category_exploration(): void
    {
        $cat = ProductCategory::create(['name' => '體重管理', 'slug' => 'slimming']);
        $product = Product::create([
            'name' => 'P', 'slug' => 'p', 'price' => 500,
            'combo_price' => 500, 'vip_price' => 500, 'is_active' => true,
        ]);
        $product->categories()->attach($cat);

        $res = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer' => ['name' => 'X', 'email' => 'x@e.com', 'phone' => '0912345678'],
            'payment_method' => 'cod',
            'shipping_method' => 'home_delivery',
            'shipping_name' => 'X', 'shipping_phone' => '0912345678',
            'shipping_address' => 'addr',
        ]);

        $this->assertContains(AchievementCatalog::EXPLORE_SLIMMING, $res->json('_achievements'));
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson('/api/customer/dashboard')->assertStatus(401);
    }

    public function test_dashboard_returns_gamification_state(): void
    {
        $customer = Customer::create([
            'name' => 'Actor', 'email' => 'actor@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
            'total_orders' => 2, 'total_spent' => 3000,
            'streak_days' => 5, 'last_active_date' => now()->subDay()->toDateString(),
        ]);

        $token = $customer->createToken('t')->plainTextToken;

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/customer/dashboard');

        $res->assertOk()
            ->assertJsonPath('customer.email', 'actor@e.com')
            ->assertJsonPath('customer.streak_days', 6)
            ->assertJsonStructure([
                'customer' => ['streak_days', 'total_orders', 'total_spent', 'activation_progress'],
                'achievements' => ['earned', 'catalog', 'progress'],
                'outfits' => ['owned', 'catalog', 'backdrops'],
            ])
            // Customer has 2 orders + 3000 spent + 6-day streak after the bump:
            // ORDER_3 (target 3) → 2/3, SPEND_5K (target 5000) → 3000/5000,
            // STREAK_7 (target 7) → 6/7. Progress map should reflect these.
            ->assertJsonPath('achievements.progress.order_3.current', 2)
            ->assertJsonPath('achievements.progress.order_3.target', 3)
            ->assertJsonPath('achievements.progress.spend_5k.current', 3000)
            ->assertJsonPath('achievements.progress.streak_7.current', 6)
            // Binary achievements (no progress key) must be absent
            ->assertJsonMissingPath('achievements.progress.first_browse');
    }

    public function test_set_outfit_requires_ownership(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $token = $customer->createToken('t')->plainTextToken;

        // Not owned → rejected
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/mascot/outfit', ['code' => 'sunglasses'])
            ->assertStatus(422);

        // Give them the outfit
        \App\Models\MascotOutfit::create([
            'customer_id' => $customer->id, 'code' => 'sunglasses', 'unlocked_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/mascot/outfit', ['code' => 'sunglasses'])
            ->assertOk();

        $this->assertSame('sunglasses', $customer->fresh()->current_outfit);
    }

    public function test_activation_mark_awards_browse(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $token = $customer->createToken('t')->plainTextToken;

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/activation', ['step' => 'first_browse']);

        $res->assertOk()->assertJsonPath('_achievement', AchievementCatalog::FIRST_BROWSE);
        $this->assertTrue($customer->fresh()->activation_progress['first_browse']);
    }

    private function assertActivationAwards(string $step, string $expectedCode): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => "a-{$step}@e.com", 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $token = $customer->createToken('t')->plainTextToken;

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/activation', ['step' => $step]);

        $res->assertOk()->assertJsonPath('_achievement', $expectedCode);
        $this->assertTrue($customer->fresh()->activation_progress[$step]);
    }

    public function test_activation_awards_first_article(): void
    {
        $this->assertActivationAwards('first_article', AchievementCatalog::FIRST_ARTICLE);
    }

    public function test_activation_awards_first_brand(): void
    {
        $this->assertActivationAwards('first_brand', AchievementCatalog::FIRST_BRAND);
    }

    public function test_activation_awards_first_cart(): void
    {
        $this->assertActivationAwards('first_cart', AchievementCatalog::FIRST_CART);
    }

    public function test_activation_awards_first_mascot(): void
    {
        $this->assertActivationAwards('first_mascot', AchievementCatalog::FIRST_MASCOT);
    }

    public function test_activation_awards_first_order_step(): void
    {
        $this->assertActivationAwards('first_order', AchievementCatalog::FIRST_ORDER);
    }

    public function test_activation_mark_is_idempotent(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'idem@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $token = $customer->createToken('t')->plainTextToken;

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/activation', ['step' => 'first_article']);
        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/activation', ['step' => 'first_article']);

        $first->assertOk()->assertJsonPath('_achievement', AchievementCatalog::FIRST_ARTICLE);
        $second->assertOk()->assertJsonPath('_achievement', null);
    }

    public function test_activation_rejects_unknown_step(): void
    {
        $customer = Customer::create([
            'name' => 'A', 'email' => 'unknown@e.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $token = $customer->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/customer/activation', ['step' => 'first_moon_landing'])
            ->assertStatus(422);
    }
}
