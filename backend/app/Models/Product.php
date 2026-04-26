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

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_product')
            ->withPivot('mention_count')
            ->withTimestamps();
    }

    /** Bundles this product appears in (as buy or gift item). */
    public function bundles()
    {
        return $this->belongsToMany(Bundle::class, 'bundle_product')
            ->withPivot('role', 'quantity', 'sort_order');
    }

    /**
     * Products visible to customers. Bundles are surfaced separately via
     * /bundles/{slug}; products themselves are always listed at their
     * regular 3-tier price regardless of bundle participation.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_active', true);
    }
}
