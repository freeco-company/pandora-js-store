<?php

namespace App\Observers;

use App\Models\Blacklist;
use App\Models\Order;
use App\Services\DiscordNotifier;

/**
 * Order lifecycle hooks:
 *  - saving:  auto-blacklist customer when COD order transitions to cod_no_pickup
 *  - created: fire Discord webhook so the team sees new orders in real time
 */
class OrderObserver
{
    public function saving(Order $order): void
    {
        if (! $order->exists) return;
        if (! $order->isDirty('status')) return;
        if ($order->status !== 'cod_no_pickup') return;
        if ($order->payment_method !== 'cod') return;

        $order->loadMissing('customer');
        Blacklist::blockForCodNoPickup($order);
    }

    public function created(Order $order): void
    {
        $n = DiscordNotifier::orders();
        if (! $n->isEnabled()) return;

        $order->loadMissing('customer', 'items');

        $payLabels = [
            'ecpay_credit'  => '信用卡',
            'cod'           => '貨到付款',
            'bank_transfer' => '銀行轉帳',
        ];
        $shipLabels = [
            'home_delivery' => '宅配到府',
            'cvs_711'       => '7-11 取貨',
            'cvs_family'    => '全家 取貨',
        ];

        $items = $order->items
            ->map(fn ($i) => "• {$i->product_name} ×{$i->quantity}")
            ->implode("\n");

        $n->embed(
            title: "🛒 新訂單 · {$order->order_number}",
            description: "**客戶**: " . ($order->customer->name ?? '—') . " · " . ($order->customer->email ?? '')
                . "\n**金額**: NT$" . number_format((int) $order->total)
                . "\n**付款**: " . ($payLabels[$order->payment_method] ?? $order->payment_method)
                . "\n**配送**: " . ($shipLabels[$order->shipping_method] ?? $order->shipping_method)
                . "\n\n" . $items,
            fields: [],
            color: 0x4A9D5F,
        );
    }
}
