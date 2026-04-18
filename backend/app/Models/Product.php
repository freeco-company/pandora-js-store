<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'short_description',
        'price', 'combo_price', 'vip_price', 'sale_price',
        'sku', 'hf_cert_no', 'hf_cert_claim', 'badges',
        'stock_quantity', 'stock_status', 'weight',
        'image', 'gallery', 'is_active', 'sort_order', 'wp_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'combo_price' => 'decimal:2',
        'vip_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'badges' => 'array',
        'gallery' => 'array',
        'is_active' => 'boolean',
        'weight' => 'decimal:2',
    ];

    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'product_product_category');
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_product')
            ->withPivot('campaign_price', 'sort_order');
    }

    /**
     * Products visible to customers right now:
     *   - is_active = true
     *   - AND (no campaign associations OR at least one active campaign)
     *
     * Products whose campaigns are all past/future are hidden — they only
     * surface during the campaign's running window.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('campaigns')
                  ->orWhereHas('campaigns', fn ($qq) => $qq->active());
            });
    }

    /**
     * Currently-running campaign this product belongs to.
     * Only returns during active period (start_at <= now < end_at).
     */
    public function getActiveCampaignAttribute(): ?Campaign
    {
        if ($this->relationLoaded('campaigns')) {
            return $this->campaigns->first(fn ($c) => $c->isRunning());
        }
        return $this->campaigns()->active()->first();
    }

    /**
     * Whether this product is part of a currently-running campaign.
     * Appended to every API response so the frontend cart can apply
     * the campaign VIP override rule.
     */
    public function getIsCampaignAttribute(): bool
    {
        if ($this->relationLoaded('campaigns')) {
            return $this->campaigns->contains(fn ($c) => $c->isRunning());
        }
        return $this->campaigns()->active()->exists();
    }

    protected $appends = ['is_campaign'];
}
