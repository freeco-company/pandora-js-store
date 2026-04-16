<?php

namespace App\Observers;

use App\Models\Blacklist;
use App\Models\Order;

/**
 * Auto-blacklist a customer when their COD order is marked cod_no_pickup.
 * Hook: Order::saving so we catch the transition *before* persist.
 */
class OrderObserver
{
    public function saving(Order $order): void
    {
        if (! $order->exists) return;                          // only on updates
        if (! $order->isDirty('status')) return;
        if ($order->status !== 'cod_no_pickup') return;
        if ($order->payment_method !== 'cod') return;

        $order->loadMissing('customer');
        Blacklist::blockForCodNoPickup($order);
    }
}
