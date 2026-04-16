<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $fillable = ['email', 'phone', 'reason', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Check if a given email or phone is blacklisted.
     */
    public static function isBlacklisted(?string $email, ?string $phone): bool
    {
        return static::where('is_active', true)
            ->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->orWhere('email', $email);
                }
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->exists();
    }

    /**
     * Permanently blacklist a customer for COD no-pickup.
     * Called from OrderObserver when an order transitions to status=cod_no_pickup.
     * Idempotent — creating twice is a no-op.
     */
    public static function blockForCodNoPickup(Order $order): self
    {
        return static::firstOrCreate(
            [
                'email' => $order->customer?->email,
                'phone' => $order->shipping_phone ?? $order->customer?->phone,
            ],
            [
                'reason'    => "貨到付款未取件（訂單 {$order->order_number}）",
                'is_active' => true,
            ],
        );
    }
}
