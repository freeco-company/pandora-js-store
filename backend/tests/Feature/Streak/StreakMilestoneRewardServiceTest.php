<?php

namespace Tests\Feature\Streak;

use App\Models\Achievement;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerDailyStreak;
use App\Models\CustomerStreakMilestoneReward;
use App\Services\AchievementCatalog;
use App\Services\Streak\DailyLoginStreakService;
use App\Services\Streak\StreakMilestoneRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SPEC-streak-milestone-rewards (mother) — coverage for milestone reward
 * unlocks. Mirrors pandora-meal `StreakMilestoneRewardServiceTest` but uses
 * the mother's reward carriers (Achievement + Coupon).
 */
class StreakMilestoneRewardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 9, 0, 0, 'Asia/Taipei'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Tester',
            'email' => 't' . uniqid() . '@example.com',
            'phone' => '0911' . random_int(100000, 999999),
            'password' => bcrypt('x'),
        ], $attrs));
    }

    public function test_milestone_1_awards_streak_1_achievement_only_no_coupon(): void
    {
        $customer = $this->makeCustomer();
        $service = app(StreakMilestoneRewardService::class);

        $result = $service->unlockForMilestone($customer, 1);

        $this->assertSame([AchievementCatalog::STREAK_1], $result['achievements_awarded']);
        $this->assertNull($result['coupon_code']);
        $this->assertNull($result['coupon_value']);
        $this->assertFalse($result['already_unlocked']);

        $this->assertDatabaseHas('achievements', [
            'customer_id' => $customer->id,
            'code' => AchievementCatalog::STREAK_1,
        ]);
        $this->assertDatabaseCount('coupons', 0);
        $this->assertDatabaseHas('customer_streak_milestone_rewards', [
            'customer_id' => $customer->id,
            'streak_days' => 1,
            'coupon_id' => null,
        ]);
    }

    public function test_milestone_3_awards_streak_3_achievement_no_coupon(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 3);

        $this->assertSame([AchievementCatalog::STREAK_3], $r['achievements_awarded']);
        $this->assertNull($r['coupon_code']);
    }

    public function test_milestone_7_awards_streak_7_achievement(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 7);

        $this->assertSame([AchievementCatalog::STREAK_7], $r['achievements_awarded']);
        $this->assertNull($r['coupon_code']);
    }

    public function test_milestone_14_awards_streak_14_achievement(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 14);

        $this->assertSame([AchievementCatalog::STREAK_14], $r['achievements_awarded']);
        $this->assertNull($r['coupon_code']);
    }

    public function test_milestone_21_issues_50_off_coupon(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 21);

        $this->assertSame([AchievementCatalog::STREAK_21], $r['achievements_awarded']);
        $this->assertNotNull($r['coupon_code']);
        $this->assertSame(50, $r['coupon_value']);
        $this->assertStringStartsWith('STREAK21-' . $customer->id . '-', $r['coupon_code']);

        $coupon = Coupon::where('code', $r['coupon_code'])->firstOrFail();
        $this->assertSame('fixed', $coupon->type);
        $this->assertSame(1, $coupon->max_uses);
        $this->assertTrue($coupon->is_active);
        $this->assertSame('600.00', (string) $coupon->min_amount);
    }

    public function test_milestone_30_awards_streak_30_no_coupon(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 30);

        $this->assertSame([AchievementCatalog::STREAK_30], $r['achievements_awarded']);
        $this->assertNull($r['coupon_code']);
    }

    public function test_milestone_60_issues_100_off_coupon(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 60);

        $this->assertSame([AchievementCatalog::STREAK_60], $r['achievements_awarded']);
        $this->assertSame(100, $r['coupon_value']);
        $coupon = Coupon::where('code', $r['coupon_code'])->firstOrFail();
        $this->assertSame('1200.00', (string) $coupon->min_amount);
    }

    public function test_milestone_100_issues_200_off_coupon(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 100);

        $this->assertSame([AchievementCatalog::STREAK_100], $r['achievements_awarded']);
        $this->assertSame(200, $r['coupon_value']);
        $coupon = Coupon::where('code', $r['coupon_code'])->firstOrFail();
        $this->assertSame('2000.00', (string) $coupon->min_amount);
    }

    public function test_unknown_streak_returns_empty_result_no_side_effects(): void
    {
        $customer = $this->makeCustomer();
        $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($customer, 5);

        $this->assertSame([], $r['achievements_awarded']);
        $this->assertNull($r['coupon_code']);
        $this->assertDatabaseCount('coupons', 0);
        $this->assertDatabaseCount('customer_streak_milestone_rewards', 0);
        $this->assertDatabaseCount('achievements', 0);
    }

    public function test_unlock_is_idempotent_re_call_does_not_double_issue_coupon(): void
    {
        $customer = $this->makeCustomer();
        $service = app(StreakMilestoneRewardService::class);

        $first = $service->unlockForMilestone($customer, 21);
        $second = $service->unlockForMilestone($customer, 21);

        $this->assertFalse($first['already_unlocked']);
        $this->assertTrue($second['already_unlocked']);
        $this->assertSame($first['coupon_code'], $second['coupon_code']);
        $this->assertDatabaseCount('coupons', 1);
        $this->assertDatabaseCount('customer_streak_milestone_rewards', 1);
    }

    public function test_unlocked_for_returns_history_in_order(): void
    {
        $customer = $this->makeCustomer();
        $service = app(StreakMilestoneRewardService::class);

        $service->unlockForMilestone($customer, 7);
        $service->unlockForMilestone($customer, 21);
        $service->unlockForMilestone($customer, 1);

        $history = $service->unlockedFor($customer);
        $this->assertCount(3, $history);
        $this->assertSame(1, $history[0]['streak_days']);
        $this->assertSame(7, $history[1]['streak_days']);
        $this->assertSame(21, $history[2]['streak_days']);
        $this->assertNull($history[0]['coupon_code']);
        $this->assertNull($history[1]['coupon_code']);
        $this->assertNotNull($history[2]['coupon_code']);
        $this->assertSame(50, $history[2]['coupon_value']);
    }

    public function test_record_login_at_milestone_attaches_unlocks_payload(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        // Day 1 = milestone 1 — first login already triggers unlock.
        $r = $svc->recordLogin($customer);

        $this->assertTrue($r['is_milestone']);
        $this->assertNotNull($r['unlocks']);
        $this->assertSame(1, $r['unlocks']['streak_days']);
        $this->assertSame([AchievementCatalog::STREAK_1], $r['unlocks']['achievements_awarded']);
        $this->assertNull($r['unlocks']['coupon_code']);
    }

    public function test_record_login_non_milestone_unlocks_is_null(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        $svc->recordLogin($customer); // day 1 — milestone
        Carbon::setTestNow(Carbon::create(2026, 5, 6, 9, 0, 0, 'Asia/Taipei'));
        $r = $svc->recordLogin($customer); // day 2 — NOT a milestone

        $this->assertSame(2, $r['streak']);
        $this->assertFalse($r['is_milestone']);
        $this->assertNull($r['unlocks']);
    }

    public function test_record_login_same_day_second_call_does_not_re_unlock(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        $svc->recordLogin($customer); // day 1 — unlock
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 18, 0, 0, 'Asia/Taipei'));
        $r = $svc->recordLogin($customer); // same day — no-op

        $this->assertFalse($r['is_first_today']);
        $this->assertFalse($r['is_milestone']);
        $this->assertNull($r['unlocks']);
        $this->assertDatabaseCount('customer_streak_milestone_rewards', 1);
    }

    public function test_streak_today_endpoint_returns_unlocks_at_milestone(): void
    {
        $customer = $this->makeCustomer();
        $token = $customer->createToken('test')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/streak/today');

        $resp->assertOk()
            ->assertJsonPath('current_streak', 1)
            ->assertJsonPath('is_milestone', true)
            ->assertJsonPath('unlocks.streak_days', 1)
            ->assertJsonPath('unlocks.achievements_awarded', [AchievementCatalog::STREAK_1])
            ->assertJsonPath('unlocks.coupon_code', null);
    }
}
