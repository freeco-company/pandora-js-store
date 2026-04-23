<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'image', 'mobile_image', 'link', 'sort_order',
        'is_active', 'starts_at', 'ends_at',
        'image_width', 'image_height',
        'mobile_image_width', 'mobile_image_height',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'image_width' => 'integer',
        'image_height' => 'integer',
        'mobile_image_width' => 'integer',
        'mobile_image_height' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
