<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'content', 'excerpt', 'featured_image',
        'source_url', 'source_type', 'status', 'is_pinned', 'sort_order',
        'published_at', 'promo_ends_at', 'rewritten_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'promo_ends_at' => 'datetime',
        'rewritten_at' => 'datetime',
        'is_pinned' => 'boolean',
    ];

    public function categories()
    {
        return $this->belongsToMany(ArticleCategory::class, 'article_article_category');
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'article_product')
            ->withPivot('mention_count')
            ->withTimestamps();
    }
}
