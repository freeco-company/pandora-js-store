<?php

namespace App\Services\Gamification;

use App\Models\Achievement;
use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Apply a `gamification.achievement_awarded` webhook from py-service.
 *
 * Used for cross-app achievements — e.g. a 朵朵 streak achievement that
 * 母艦 wants to mirror locally so the customer's account page badge wall
 * shows it. Source of truth is py-service.
 *
 * Idempotent on (customer_id, code). 母艦 has its own `achievements` table
 * with the same shape; we use `awarded_at` for the timestamp and write a
 * synthetic `code` matching whatever py-service used (e.g. `meal.streak_7`).
 *
 * Achievement code from py-service may NOT exist in 母艦's `AchievementCatalog`
 * (it's a 朵朵-side code). We allow the foreign code anyway so the badge
 * wall reflects cross-app accomplishments.
 */
class AchievementMirror
{
    /**
     * @param  array<string, mixed>  $payload  Webhook payload (achievement_awarded
     *                                         event). Expected: code, name, tier,
     *                                         source_app, occurred_at.
     */
    public function applyAwarded(string $pandoraUserUuid, array $payload): bool
    {
        if ($pandoraUserUuid === '') {
            return false;
        }
        $code = (string) ($payload['code'] ?? '');
        if ($code === '') {
            return false;
        }

        $customer = Customer::where('pandora_user_uuid', $pandoraUserUuid)->first();
        if ($customer === null) {
            return false;
        }

        $existing = Achievement::where('customer_id', $customer->id)
            ->where('code', $code)
            ->first();
        if ($existing !== null) {
            return false;
        }

        $occurredAt = $payload['occurred_at'] ?? null;
        $awardedAt = is_string($occurredAt) ? Carbon::parse($occurredAt) : Carbon::now();

        Achievement::create([
            'customer_id' => $customer->id,
            'code' => $code,
            'awarded_at' => $awardedAt,
        ]);

        return true;
    }
}
