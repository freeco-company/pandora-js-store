<?php

namespace App\Services\Gamification;

use App\Models\Customer;

/**
 * Apply a `gamification.outfit_unlocked` webhook from py-service to the local
 * customers.outfits_owned mirror. ADR-009 Phase B inbound.
 *
 * Idempotent: codes already in `outfits_owned` are skipped, so replay or
 * double-fire is safe. Returns the count of newly merged codes.
 *
 * Customer not found → silent drop (return 0). Webhook will fire again on
 * next outfit_unlocked event.
 */
class OutfitMirror
{
    /**
     * @param  array<string, mixed>  $payload  Webhook payload. Expected keys:
     *                                         codes (list<string>), awarded_via,
     *                                         trigger_level, occurred_at.
     */
    public function applyUnlocked(string $pandoraUserUuid, array $payload): int
    {
        if ($pandoraUserUuid === '') {
            return 0;
        }
        $codes = $payload['codes'] ?? null;
        if (! is_array($codes) || $codes === []) {
            return 0;
        }

        $customer = Customer::where('pandora_user_uuid', $pandoraUserUuid)->first();
        if ($customer === null) {
            return 0;
        }

        $owned = (array) ($customer->outfits_owned ?? ['none']);
        $added = 0;
        foreach ($codes as $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }
            if (! in_array($code, $owned, true)) {
                $owned[] = $code;
                $added++;
            }
        }

        if ($added > 0) {
            $customer->fill(['outfits_owned' => $owned]);
            $customer->save();
        }

        return $added;
    }
}
