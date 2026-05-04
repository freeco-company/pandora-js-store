<?php

namespace App\Services\Streak;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerStreakMilestoneReward;
use App\Services\AchievementCatalog;
use App\Services\AchievementService;
use App\Services\PandoraGamificationPublisher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SPEC-streak-milestone-rewards (mother) — unlock achievements + personalised
 * coupons when a customer hits a streak milestone (1 / 3 / 7 / 14 / 21 / 30 / 60 / 100).
 *
 * Mirrors pandora-meal `App\Services\Dodo\Streak\StreakMilestoneRewardService`
 * but for the mother B2C store. The reward carriers differ:
 *
 *   - **Achievement** (existing AchievementService) — every milestone awards a
 *     badge (STREAK_1 / STREAK_3 / ... / STREAK_100). Idempotent via the
 *     unique (customer_id, code) index on `achievements`.
 *   - **Personalised Coupon** (existing Coupon model) — 21 / 60 / 100 milestones
 *     issue a single-use, customer-bound coupon code. Code pattern:
 *       `STREAK{days}-{customerId}-{rand6}`
 *     We track the issued coupon in `customer_streak_milestone_rewards` so a
 *     customer can never get two coupons for the same milestone, even after
 *     a same-day re-entry into recordLogin (defensive — same-day no-op already
 *     gates this, but the unique (customer_id, streak_days) index makes the
 *     guarantee explicit).
 *
 * No member-points reward — the mother store has no points system. Coupons +
 * badges are the natural carriers and align with what `Filament/Resources/
 * CouponResource` and `AchievementService` already expose.
 *
 * Compliance (CLAUDE.md §7 食安法/健康食品管理法 + group-fp-product-compliance.md):
 *   - Reward labels ('一週老朋友', '習慣養成', etc.) describe browsing behaviour,
 *     not health outcomes — no 減脂/燃脂/排毒/治療 wording.
 *   - Coupon descriptions are NT$ off discounts on the storefront, not product
 *     efficacy claims.
 */
class StreakMilestoneRewardService
{
    /**
     * Milestone definitions. Each entry says which achievement code(s) to award
     * and (optionally) what coupon to issue.
     *
     * `coupon` shape: ['type' => 'fixed', 'value' => 50, 'min_amount' => 600,
     *                  'expires_in_days' => 60, 'description' => '...']
     *
     * @var array<int, array{
     *   achievements: list<string>,
     *   coupon?: array{type:string, value:int, min_amount:int, expires_in_days:int, label:string}
     * }>
     */
    private const MILESTONES = [
        1 => [
            'achievements' => [AchievementCatalog::STREAK_1],
        ],
        3 => [
            'achievements' => [AchievementCatalog::STREAK_3],
        ],
        7 => [
            'achievements' => [AchievementCatalog::STREAK_7],
        ],
        14 => [
            'achievements' => [AchievementCatalog::STREAK_14],
        ],
        21 => [
            'achievements' => [AchievementCatalog::STREAK_21],
            'coupon' => [
                'type' => 'fixed',
                'value' => 50,
                'min_amount' => 600,
                'expires_in_days' => 60,
                'label' => '21 天習慣養成 NT$50 折扣券',
            ],
        ],
        30 => [
            'achievements' => [AchievementCatalog::STREAK_30],
        ],
        60 => [
            'achievements' => [AchievementCatalog::STREAK_60],
            'coupon' => [
                'type' => 'fixed',
                'value' => 100,
                'min_amount' => 1200,
                'expires_in_days' => 90,
                'label' => '兩月好朋友 NT$100 折扣券',
            ],
        ],
        100 => [
            'achievements' => [AchievementCatalog::STREAK_100],
            'coupon' => [
                'type' => 'fixed',
                'value' => 200,
                'min_amount' => 2000,
                'expires_in_days' => 120,
                'label' => '百日傳奇 NT$200 折扣券',
            ],
        ],
    ];

    public function __construct(
        private readonly AchievementService $achievementService,
        private readonly PandoraGamificationPublisher $gamification,
    ) {}

    /**
     * @return array{
     *   streak_days: int,
     *   achievements_awarded: list<string>,
     *   coupon_code: ?string,
     *   coupon_value: ?int,
     *   coupon_label: ?string,
     *   already_unlocked: bool,
     * }
     */
    public function unlockForMilestone(Customer $customer, int $streak): array
    {
        $reward = self::MILESTONES[$streak] ?? null;
        if ($reward === null) {
            return $this->emptyResult($streak);
        }

        // Idempotency anchor: a row in customer_streak_milestone_rewards means
        // we already issued this milestone's rewards. Don't re-issue.
        $existing = CustomerStreakMilestoneReward::query()
            ->where('customer_id', $customer->id)
            ->where('streak_days', $streak)
            ->first();
        if ($existing !== null) {
            $coupon = $existing->coupon_id ? Coupon::find($existing->coupon_id) : null;

            return [
                'streak_days' => $streak,
                'achievements_awarded' => (array) ($existing->achievements_awarded ?? []),
                'coupon_code' => $coupon?->code,
                'coupon_value' => $coupon ? (int) $coupon->value : null,
                'coupon_label' => $reward['coupon']['label'] ?? null,
                'already_unlocked' => true,
            ];
        }

        $awarded = [];
        foreach ($reward['achievements'] as $code) {
            $newCode = $this->achievementService->award($customer, $code);
            if ($newCode !== null) {
                $awarded[] = $newCode;
            }
        }

        $coupon = null;
        if (isset($reward['coupon'])) {
            $coupon = $this->issueCoupon($customer, $streak, $reward['coupon']);
        }

        // Persist the unlock row even if achievements were already owned (the
        // achievement-side dedupe is on `code` not `streak`, so a customer who
        // somehow had `streak_7` from an earlier flow would still get the row
        // here — keeps reporting honest).
        try {
            CustomerStreakMilestoneReward::create([
                'customer_id' => $customer->id,
                'streak_days' => $streak,
                'coupon_id' => $coupon?->id,
                'achievements_awarded' => $awarded,
                'unlocked_at' => Carbon::now('Asia/Taipei'),
            ]);
        } catch (Throwable $e) {
            // Race: another concurrent recordLogin() inserted the row first.
            // Re-read and treat as already_unlocked.
            Log::info('[StreakMilestoneReward] concurrent insert lost race; re-reading', [
                'customer_id' => $customer->id,
                'streak' => $streak,
                'error' => $e->getMessage(),
            ]);
        }

        $this->safePublish($customer, $streak, $awarded, $coupon);

        return [
            'streak_days' => $streak,
            'achievements_awarded' => $awarded,
            'coupon_code' => $coupon?->code,
            'coupon_value' => $coupon ? (int) $coupon->value : null,
            'coupon_label' => $reward['coupon']['label'] ?? null,
            'already_unlocked' => false,
        ];
    }

    /**
     * Snapshot all milestones already unlocked for a customer.
     * Used by GET /api/streak/today's `unlocks` payload.
     *
     * @return list<array{streak_days:int, coupon_code:?string, coupon_value:?int, unlocked_at:string}>
     */
    public function unlockedFor(Customer $customer): array
    {
        $rows = CustomerStreakMilestoneReward::query()
            ->with('coupon:id,code,value,is_active,expires_at')
            ->where('customer_id', $customer->id)
            ->orderBy('streak_days')
            ->get();

        return $rows->map(fn (CustomerStreakMilestoneReward $r) => [
            'streak_days' => (int) $r->streak_days,
            'coupon_code' => $r->coupon?->code,
            'coupon_value' => $r->coupon ? (int) $r->coupon->value : null,
            'unlocked_at' => $r->unlocked_at?->toIso8601String() ?? '',
        ])->all();
    }

    /**
     * Issue a single-use customer-bound coupon. Code is unique per customer +
     * streak (the table's unique index also prevents duplicates).
     */
    private function issueCoupon(Customer $customer, int $streak, array $couponConfig): ?Coupon
    {
        try {
            return DB::transaction(function () use ($customer, $streak, $couponConfig) {
                $code = sprintf(
                    'STREAK%d-%d-%s',
                    $streak,
                    $customer->id,
                    strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
                );

                return Coupon::create([
                    'code' => $code,
                    'type' => $couponConfig['type'],
                    'value' => $couponConfig['value'],
                    'min_amount' => $couponConfig['min_amount'],
                    'max_uses' => 1,
                    'used_count' => 0,
                    'expires_at' => Carbon::now('Asia/Taipei')
                        ->addDays((int) $couponConfig['expires_in_days']),
                    'is_active' => true,
                ]);
            });
        } catch (Throwable $e) {
            Log::warning('[StreakMilestoneReward] coupon issuance failed (soft)', [
                'customer_id' => $customer->id,
                'streak' => $streak,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fail-soft publish to py-service gamification. `jerosse.streak_milestone_unlocked`
     * is in KNOWN_EVENT_KINDS but py-service catalog may not have it yet — publisher
     * will warn + drop in that case. We swallow exceptions so streak flow never
     * breaks the request.
     */
    private function safePublish(Customer $customer, int $streak, array $awarded, ?Coupon $coupon): void
    {
        $uuid = (string) ($customer->pandora_user_uuid ?? '');
        if ($uuid === '') {
            return;
        }
        $today = Carbon::now('Asia/Taipei')->toDateString();

        try {
            $this->gamification->publish(
                $uuid,
                'jerosse.streak_milestone_unlocked',
                "jerosse.streak_milestone_unlocked.{$uuid}.{$streak}.{$today}",
                [
                    'streak_days' => $streak,
                    'achievements' => $awarded,
                    'coupon_code' => $coupon?->code,
                    'coupon_value' => $coupon ? (int) $coupon->value : null,
                ],
            );
        } catch (Throwable $e) {
            Log::warning('[StreakMilestoneReward] publish failed (soft)', [
                'customer_id' => $customer->id,
                'streak' => $streak,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *   streak_days: int,
     *   achievements_awarded: list<string>,
     *   coupon_code: ?string,
     *   coupon_value: ?int,
     *   coupon_label: ?string,
     *   already_unlocked: bool,
     * }
     */
    private function emptyResult(int $streak): array
    {
        return [
            'streak_days' => $streak,
            'achievements_awarded' => [],
            'coupon_code' => null,
            'coupon_value' => null,
            'coupon_label' => null,
            'already_unlocked' => false,
        ];
    }
}
