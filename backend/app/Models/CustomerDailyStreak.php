<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPEC-cross-app-streak Phase 1.C — per-App 每日登入 streak（母艦）。
 *
 * 一 customer 一 row。詳見 migration & DailyLoginStreakService doc。
 *
 * @property int $id
 * @property int $customer_id
 * @property int $current_streak
 * @property int $longest_streak
 * @property \Illuminate\Support\Carbon|null $last_login_date
 */
class CustomerDailyStreak extends Model
{
    protected $fillable = [
        'customer_id',
        'current_streak',
        'longest_streak',
        'last_login_date',
    ];

    protected $casts = [
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'last_login_date' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
