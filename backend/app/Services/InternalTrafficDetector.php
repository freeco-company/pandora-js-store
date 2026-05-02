<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for "is this visit / cart event coming from a
 * team member's testing browser?"
 *
 * Reads config('analytics.internal_emails' / 'internal_ips') — both are
 * env-driven, so production can be reconfigured without a deploy by
 * editing .env and reloading PHP-FPM.
 *
 * Customer-id resolution is cached for the request lifecycle; the email
 * → id lookup is cheap but called per visit on busy pages, so caching
 * cuts redundant `customers` selects.
 */
class InternalTrafficDetector
{
    /** @var array<int, int>|null */
    private ?array $internalCustomerIds = null;

    public function isInternalIp(?string $ip): bool
    {
        if (! $ip) return false;
        $list = (array) config('analytics.internal_ips', []);
        return in_array($ip, $list, true);
    }

    public function isInternalEmail(?string $email): bool
    {
        if (! $email) return false;
        $list = (array) config('analytics.internal_emails', []);
        return in_array(strtolower($email), array_map('strtolower', $list), true);
    }

    public function isInternalCustomerId(?int $customerId): bool
    {
        if (! $customerId) return false;
        return in_array($customerId, $this->getInternalCustomerIds(), true);
    }

    /**
     * @return array<int, int>
     */
    public function getInternalCustomerIds(): array
    {
        if ($this->internalCustomerIds !== null) {
            return $this->internalCustomerIds;
        }

        $emails = (array) config('analytics.internal_emails', []);
        if (empty($emails)) {
            return $this->internalCustomerIds = [];
        }

        // Short cache so hot pages don't re-query for every visit. 5 min
        // is fine — when we add a new internal email we tolerate up to
        // 5 min of pollution, which is well below the time it takes a
        // dashboard refresh to surface anything material.
        return $this->internalCustomerIds = Cache::remember(
            'analytics:internal_customer_ids',
            now()->addMinutes(5),
            fn () => Customer::whereIn('email', $emails)->pluck('id')->all(),
        );
    }
}
