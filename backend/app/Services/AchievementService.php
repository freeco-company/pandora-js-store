<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    public function __construct(
        private readonly ?PandoraGamificationPublisher $gamificationPublisher = null,
    ) {}

    /**
     * Award an achievement to a customer.
     * Idempotent — returns the code if newly awarded, null if already had it.
     *
     * On successful new grant, ALSO publishes a `jerosse.achievement_awarded`
     * event to py-service gamification so cross-app group level / outfit
     * unlock can fire (ADR-009 §2-3). Publisher is env-gated noop when
     * PANDORA_GAMIFICATION_BASE_URL is empty, so dev / CI is unaffected.
     */
    public function award(Customer $customer, string $code): ?string
    {
        if (!AchievementCatalog::get($code)) return null;

        try {
            Achievement::create([
                'customer_id' => $customer->id,
                'code' => $code,
                'awarded_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return null;
        }

        $this->publishGamification($customer, $code);

        return $code;
    }

    private function publishGamification(Customer $customer, string $code): void
    {
        $publisher = $this->gamificationPublisher ?? app(PandoraGamificationPublisher::class);
        $uuid = (string) ($customer->pandora_user_uuid ?? '');
        if ($uuid === '') {
            return;
        }
        try {
            $publisher->publish(
                pandoraUserUuid: $uuid,
                eventKind: 'jerosse.achievement_awarded',
                idempotencyKey: 'jerosse.achievement.'.$customer->id.'.'.$code,
                metadata: ['code' => $code],
            );
        } catch (\Throwable $e) {
            // 5xx bubbles up here only because publish() throws on 5xx for queue
            // retry; AchievementService is called synchronously so we swallow
            // and log to avoid breaking the order/checkout flow. Ops sees the
            // log and can replay via reconcile/manual.
            Log::warning('[AchievementService] gamification publish failed; continuing', [
                'customer_id' => $customer->id,
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Award multiple codes, return the ones newly awarded.
     */
    public function awardMany(Customer $customer, array $codes): array
    {
        $awarded = [];
        foreach (array_unique($codes) as $code) {
            if ($c = $this->award($customer, $code)) {
                $awarded[] = $c;
            }
        }
        return $awarded;
    }

    /**
     * Update activation_progress bitmap with a step.
     */
    public function markActivation(Customer $customer, string $step): void
    {
        $progress = $customer->activation_progress ?? [];
        if (empty($progress[$step])) {
            $progress[$step] = true;
            $customer->update(['activation_progress' => $progress]);
        }
    }

    /**
     * Bump the visit streak. Returns newly-awarded streak achievement code if any.
     */
    public function bumpStreak(Customer $customer): ?string
    {
        $today = now()->toDateString();
        $last = $customer->last_active_date?->toDateString();

        if ($last === $today) return null;

        $yesterday = now()->subDay()->toDateString();
        $newStreak = ($last === $yesterday) ? $customer->streak_days + 1 : 1;

        $customer->update([
            'streak_days' => $newStreak,
            'last_active_date' => $today,
        ]);

        return match (true) {
            $newStreak === 100 => $this->award($customer, AchievementCatalog::STREAK_100),
            $newStreak === 30 => $this->award($customer, AchievementCatalog::STREAK_30),
            $newStreak === 7 => $this->award($customer, AchievementCatalog::STREAK_7),
            default => null,
        };
    }
}
