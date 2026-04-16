<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MascotOutfit;

class OutfitService
{
    /**
     * Check all outfits/backdrops the customer qualifies for but hasn't unlocked.
     * Returns newly-unlocked codes (mixed outfit + backdrop).
     */
    public function checkUnlocks(Customer $customer): array
    {
        $newly = [];

        $achievementCount = $customer->achievements()->count();

        $catalog = array_merge(OutfitCatalog::all(), OutfitCatalog::backdrops());

        foreach ($catalog as $code => $meta) {
            if ($this->meetsRequirement($customer, $meta['unlock'], $achievementCount)) {
                if ($this->tryUnlock($customer, $code)) {
                    $newly[] = $code;
                }
            }
        }

        return $newly;
    }

    private function meetsRequirement(Customer $customer, array $req, int $achievementCount): bool
    {
        return match ($req['type']) {
            'orders' => $customer->total_orders >= $req['value'],
            'spend' => $customer->total_spent >= $req['value'],
            'streak' => $customer->streak_days >= $req['value'],
            'achievements' => $achievementCount >= $req['value'],
            default => false,
        };
    }

    private function tryUnlock(Customer $customer, string $code): bool
    {
        try {
            MascotOutfit::create([
                'customer_id' => $customer->id,
                'code' => $code,
                'unlocked_at' => now(),
            ]);
            return true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false;
        }
    }

    public function setCurrentOutfit(Customer $customer, ?string $code): bool
    {
        if ($code === null) {
            $customer->update(['current_outfit' => null]);
            return true;
        }
        $owned = $customer->outfits()->where('code', $code)->exists();
        if (!$owned) return false;
        $customer->update(['current_outfit' => $code]);
        return true;
    }

    public function setCurrentBackdrop(Customer $customer, ?string $code): bool
    {
        if ($code === null) {
            $customer->update(['current_backdrop' => null]);
            return true;
        }
        $owned = $customer->outfits()->where('code', $code)->exists();
        if (!$owned) return false;
        $customer->update(['current_backdrop' => $code]);
        return true;
    }
}
