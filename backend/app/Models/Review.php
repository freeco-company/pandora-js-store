<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'customer_id', 'order_id',
        'rating', 'content', 'reviewer_name',
        'is_verified_purchase', 'is_seeded', 'is_visible',
        'auto_review_reminder_sent_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_seeded' => 'boolean',
        'is_visible' => 'boolean',
        'auto_review_reminder_sent_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
