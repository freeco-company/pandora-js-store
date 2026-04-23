<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per cart interaction — mirror of GA4 add_to_cart/view_item/
 * begin_checkout events, persisted server-side so the pipeline daily
 * report can compute funnel rates without depending on the GA4 API.
 */
class CartEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id', 'customer_id', 'event_type',
        'product_id', 'bundle_id', 'quantity', 'value', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'value' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }
}
