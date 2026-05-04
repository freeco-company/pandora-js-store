<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPEC-streak-milestone-rewards — one row per (customer, streak_days) milestone.
 * Idempotency anchor for `StreakMilestoneRewardService::unlockForMilestone()`.
 */
class CustomerStreakMilestoneReward extends Model
{
    protected $fillable = [
        'customer_id',
        'streak_days',
        'coupon_id',
        'achievements_awarded',
        'unlocked_at',
    ];

    protected $casts = [
        'streak_days' => 'int',
        'achievements_awarded' => 'array',
        'unlocked_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, CustomerStreakMilestoneReward> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Coupon, CustomerStreakMilestoneReward> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
