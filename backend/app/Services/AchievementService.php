<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    /**
     * Award an achievement to a customer.
     * Idempotent — returns the code if newly awarded, null if already had it.
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
            return $code;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return null;
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
