<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderPaymentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * COD pending_confirmation 訂單流程（多段提醒）：
 *   - 3h / 24h 寄提醒（email + LINE）
 *   - 48h 未確認 → 取消、還原庫存 / coupon、通知
 */
class AutoCancelUnconfirmedOrdersCmd extends Command
{
    protected $signature = 'cod:auto-cancel-unconfirmed';
    protected $description = 'Multi-stage reminders + auto-cancel COD orders pending LINE confirmation';

    public function handle(OrderPaymentReminderService $reminder): int
    {
        $cancelHours = OrderPaymentReminderService::CANCEL_HOURS_COD;
        $cancelThreshold = now()->subHours($cancelHours);

        $stats = ['reminded' => 0, 'cancelled' => 0, 'errors' => 0];

        Order::where('status', 'pending_confirmation')
            ->where('created_at', '<', $cancelThreshold)
            ->with(['items', 'customer', 'coupon'])
            ->chunkById(100, function ($orders) use ($reminder, $cancelHours, &$stats) {
                foreach ($orders as $order) {
                    try {
                        $reminder->cancelOrder(
                            order: $order,
                            reason: "超過 {$cancelHours} 小時未在 LINE 確認出貨",
                            emailSubject: "【婕樂纖仙女館】訂單 {$order->order_number} 已自動取消",
                            emailBody: "您的訂單 {$order->order_number} 因未在期限內於 LINE 完成確認，已自動取消。如仍要購買請重新下單，謝謝。",
                        );
                        $stats['cancelled']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('[cod:auto-cancel] failed', ['order' => $order->order_number, 'msg' => $e->getMessage()]);
                    }
                }
            });

        Order::where('status', 'pending_confirmation')
            ->where('created_at', '>=', $cancelThreshold)
            ->with(['customer'])
            ->chunkById(100, function ($orders) use ($reminder, &$stats) {
                foreach ($orders as $order) {
                    try {
                        if ($reminder->processReminderStages($order, 'cod')) {
                            $stats['reminded']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('[cod:reminder] failed', ['order' => $order->order_number, 'msg' => $e->getMessage()]);
                    }
                }
            });

        $this->info("reminded {$stats['reminded']} · cancelled {$stats['cancelled']} · errors {$stats['errors']}");
        return self::SUCCESS;
    }
}
