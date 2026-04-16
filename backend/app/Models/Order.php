<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'customer_id', 'coupon_id', 'status', 'pricing_tier',
        'subtotal', 'shipping_fee', 'discount', 'total',
        'payment_method', 'payment_status', 'ecpay_trade_no',
        'shipping_method', 'shipping_name', 'shipping_phone',
        'shipping_address', 'shipping_store_id', 'shipping_store_name',
        'note', 'wp_order_id', 'abandoned_reminder_sent_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'abandoned_reminder_sent_at' => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
