<?php

namespace App\Observers;

use App\Http\Controllers\Api\OrderController;
use App\Mail\OrderCompleted;
use App\Mail\OrderPaymentConfirmed;
use App\Mail\OrderShipped;
use App\Models\Blacklist;
use App\Models\Order;
use App\Services\DiscordNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $name = $order->customer->name ?? '—';
        $email = $order->customer->email ?? '';
        DiscordNotifier::compliance()->embed(
            title: "🚫 COD 黑名單 · {$order->order_number}",
            description: "**客戶**: {$name} ({$email})\n逾期未取貨，已封鎖貨到付款",
            color: 0xE0748C,
        );
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

    /**
     * When payment_status transitions to 'paid':
     *   - bank_transfer: send the "payment confirmed" email and run celebrations
     *     (celebrations were deferred at order creation so we don't have to
     *     revoke achievements if a transfer never arrives).
     *   - ecpay_credit / cod: celebrations already handled elsewhere
     *     (PaymentController callback / OrderController::store), so only the
     *     bank_transfer path needs this hook.
     */
    public function updated(Order $order): void
    {
        $this->onPaymentPaid($order);
        $this->onShipped($order);
        $this->onCompleted($order);
    }

    /**
     * Status transition → 'completed': closes the loop with a thank-you note
     * and a review prompt. Pairs with the existing review-reminder cron
     * (which fires after a delay) — this hits the moment of confirmation
     * while satisfaction is fresh.
     */
    private function onCompleted(Order $order): void
    {
        if (! $order->wasChanged('status')) return;
        if ($order->status !== 'completed') return;
        if ($order->getOriginal('status') === 'completed') return;

        $order->loadMissing('customer');
        $email = $order->customer?->email;
        if (! $email) return;

        try {
            Mail::to($email)->send(new OrderCompleted($order));
        } catch (\Throwable $e) {
            Log::error('Failed to send completed email', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Status transition → 'shipped': notify customer with pickup code
     * (CVS) or shipping confirmation (home delivery). Without this email,
     * customers come back to /order-lookup to check status — that page
     * was the 9th most-visited path in the 14d before this was added.
     */
    private function onShipped(Order $order): void
    {
        if (! $order->wasChanged('status')) return;
        if ($order->status !== 'shipped') return;
        if ($order->getOriginal('status') === 'shipped') return;

        $order->loadMissing('customer');
        $email = $order->customer?->email;
        if (! $email) return;

        try {
            Mail::to($email)->send(new OrderShipped($order));
        } catch (\Throwable $e) {
            Log::error('Failed to send shipped email', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function onPaymentPaid(Order $order): void
    {
        if (! $order->wasChanged('payment_status')) return;
        if ($order->payment_status !== 'paid') return;
        if ($order->getOriginal('payment_status') === 'paid') return;
        if ($order->payment_method !== 'bank_transfer') return;

        $order->loadMissing('customer', 'items');

        try {
            app(OrderController::class)->runCelebrations($order);
        } catch (\Throwable $e) {
            Log::error('Failed to run celebrations on bank-transfer payment confirm', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }

        $email = $order->customer?->email;
        if ($email) {
            try {
                Mail::to($email)->send(new OrderPaymentConfirmed($order));
            } catch (\Throwable $e) {
                Log::error('Failed to send payment-confirmed email', [
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
