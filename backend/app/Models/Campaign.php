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

    public function products()
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot('campaign_price', 'sort_order')
            ->orderByPivot('sort_order');
    }
}
