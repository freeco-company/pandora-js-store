<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'image', 'banner_image',
        'start_at', 'end_at', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ─── Scopes ──────────────────────────────────────────────

    /** Currently running campaigns (is_active + within date range). */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where('end_at', '>', now());
    }

    /** Campaigns that have ended. */
    public function scopeEnded($query)
    {
        return $query->where('end_at', '<=', now());
    }

    /** Upcoming campaigns. */
    public function scopeUpcoming($query)
    {
        return $query->where('is_active', true)
            ->where('start_at', '>', now());
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isRunning(): bool
    {
        return $this->is_active
            && $this->start_at <= now()
            && $this->end_at > now();
    }

    public function hasEnded(): bool
    {
        return $this->end_at <= now();
    }

    // ─── Relations ───────────────────────────────────────────

    /** All pivot rows (both buy and gift). */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot('role', 'quantity', 'sort_order')
            ->orderByPivot('sort_order');
    }

    /** Products in the "buy" slot — these count towards the bundle price. */
    public function buyItems()
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot('role', 'quantity', 'sort_order')
            ->wherePivot('role', 'buy')
            ->orderByPivot('sort_order');
    }

    /** Products in the "gift" slot — free when the buy items are purchased. */
    public function giftItems()
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot('role', 'quantity', 'sort_order')
            ->wherePivot('role', 'gift')
            ->orderByPivot('sort_order');
    }

    // ─── Bundle pricing ──────────────────────────────────────

    /**
     * Bundle price = sum over buy items of (product VIP price × qty).
     * Gift items are free. Eager-load relations before calling for speed.
     */
    public function bundlePrice(): float
    {
        return (float) $this->buyItems->sum(
            fn ($p) => (float) ($p->vip_price ?? $p->price) * (int) $p->pivot->quantity
        );
    }

    /** Sum of buy item retail prices — for showing the "save NT$X" anchor. */
    public function bundleOriginalPrice(): float
    {
        return (float) $this->buyItems->sum(
            fn ($p) => (float) $p->price * (int) $p->pivot->quantity
        );
    }

    /** Total item count shipped in the bundle (buy qty + gift qty). */
    public function bundleItemCount(): int
    {
        return (int) $this->buyItems->sum(fn ($p) => (int) $p->pivot->quantity)
             + (int) $this->giftItems->sum(fn ($p) => (int) $p->pivot->quantity);
    }
}
