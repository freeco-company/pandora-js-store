<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Bundle is a purchasable unit under a Campaign: N buy items + M gift items,
 * priced as (sum of buy items' VIP × qty). Lives at /bundles/{slug}.
 */
class Bundle extends Model
{
    protected $fillable = [
        'campaign_id', 'name', 'slug', 'description', 'image',
        'value_price', 'original_value_price', 'custom_gifts', 'sort_order',
    ];

    protected $casts = [
        'custom_gifts' => 'array',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'bundle_product')
            ->withPivot('role', 'quantity', 'sort_order')
            ->orderByPivot('sort_order');
    }

    public function buyItems()
    {
        return $this->belongsToMany(Product::class, 'bundle_product')
            ->withPivot('role', 'quantity', 'sort_order')
            ->wherePivot('role', 'buy')
            ->orderByPivot('sort_order');
    }

    public function giftItems()
    {
        return $this->belongsToMany(Product::class, 'bundle_product')
            ->withPivot('role', 'quantity', 'sort_order')
            ->wherePivot('role', 'gift')
            ->orderByPivot('sort_order');
    }

    // ─── Pricing ─────────────────────────────────────────────

    /** Sum of buy items' VIP × qty. Gifts are free. */
    public function bundlePrice(): float
    {
        return (float) $this->buyItems->sum(
            fn ($p) => (float) ($p->vip_price ?? $p->price) * (int) $p->pivot->quantity,
        );
    }

    /** Retail anchor = sum of buy items' RRP × qty. */
    public function bundleOriginalPrice(): float
    {
        return (float) $this->buyItems->sum(
            fn ($p) => (float) $p->price * (int) $p->pivot->quantity,
        );
    }

    /**
     * 「原價值」— strikethrough anchor shown alongside 套組價.
     * Prefer new `original_value_price` (pure keyin), then legacy `value_price`,
     * else fall back to retail sum so frontend always has a number.
     */
    public function valuePrice(): float
    {
        if ($this->original_value_price !== null && (float) $this->original_value_price > 0) {
            return (float) $this->original_value_price;
        }
        if ($this->value_price !== null && (float) $this->value_price > 0) {
            return (float) $this->value_price;
        }
        return $this->bundleOriginalPrice();
    }

    // ─── Helpers ─────────────────────────────────────────────

    /** Available = parent campaign is currently running. */
    public function isAvailable(): bool
    {
        $c = $this->campaign;
        return $c && $c->isRunning();
    }
}
