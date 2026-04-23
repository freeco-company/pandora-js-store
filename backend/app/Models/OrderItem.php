<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id', 'product_id', 'product_name',
        'quantity', 'unit_price', 'subtotal', 'created_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Parse bundle context out of product_name prefix.
     * OrderController writes bundle items as `【bundle_name】product` or
     * `【bundle_name｜贈品】product`. Admin UI uses this to group rows.
     */
    public function getBundleGroupAttribute(): string
    {
        if (preg_match('/^【([^｜】]+?)(?:｜贈品)?】/u', (string) $this->product_name, $m)) {
            return $m[1];
        }
        return '單品';
    }

    public function getBundleIsGiftAttribute(): bool
    {
        return (bool) preg_match('/^【[^】]*｜贈品】/u', (string) $this->product_name);
    }

    /** product_name with 【bundle】 prefix stripped for cleaner display. */
    public function getDisplayNameAttribute(): string
    {
        return preg_replace('/^【[^】]*】\s*/u', '', (string) $this->product_name) ?: $this->product_name;
    }
}
