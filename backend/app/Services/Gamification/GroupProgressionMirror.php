<?php

namespace App\Services\Gamification;

use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Apply a `gamification.level_up` webhook from py-service to the local
 * customers row mirror. ADR-009 §2.4.
 *
 * Source of truth is py-service ledger; this just keeps customers.{group_level,
 * group_level_xp, group_level_name_*} aligned so the storefront can render
 * group-level UI without needing to call py-service per request.
 *
 * Forward-only — a stale event arriving after a newer one cannot regress
 * group_level. (py-service ledger is monotonic; if order swaps in transit we
 * accept that the higher number wins.)
 *
 * Customer not found → silent drop. Webhook will fire again next level-up;
 * for the very first sync a customer must already have a row — backfill
 * ensures every customer has pandora_user_uuid before the publisher starts
 * firing.
 */
class GroupProgressionMirror
{
    /**
     * @param  array<string, mixed>  $payload  Webhook payload — typed loosely
     *                                         because this is a wire-format
     *                                         boundary (consumer of py-service).
     */
    public function applyLevelUp(string $pandoraUserUuid, array $payload): bool
    {
        if ($pandoraUserUuid === '') {
            return false;
        }
        $customer = Customer::where('pandora_user_uuid', $pandoraUserUuid)->first();
        if ($customer === null) {
            return false;
        }

        $newLevel = (int) ($payload['new_level'] ?? 0);
        if ($newLevel <= 0) {
            return false;
        }

        $changed = false;
        if ($newLevel > (int) $customer->group_level) {
            $customer->group_level = $newLevel;
            $changed = true;
        }
        if (isset($payload['total_xp'])) {
            $totalXp = (int) $payload['total_xp'];
            if ($totalXp > (int) $customer->group_level_xp) {
                $customer->group_level_xp = $totalXp;
                $changed = true;
            }
        }
        if (isset($payload['level_name_zh']) && is_string($payload['level_name_zh'])) {
            $customer->group_level_name_zh = $payload['level_name_zh'];
            $changed = true;
        }
        if (isset($payload['level_name_en']) && is_string($payload['level_name_en'])) {
            $customer->group_level_name_en = $payload['level_name_en'];
            $changed = true;
        }

        if ($changed) {
            $customer->group_level_updated_at = Carbon::now();
            $customer->save();
        }

        return $changed;
    }
}
