<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderPaymentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Bank transfer 訂單：24h 提醒 / 48h 未付款自動取消。
 *
 * 偵測條件：
 *   - payment_method = 'bank_transfer'
 *   - payment_status = 'unpaid'
 *   - status NOT IN ('cancelled', 'refunded')
 */
class BankTransferAutoCancelCmd extends Command
{
    protected $signature = 'bank-transfer:auto-cancel
        {--remind-after=24}
        {--cancel-after=48}';
    protected $description = 'Send reminder + cancel bank transfer orders that have not been paid past thresholds';

    public function handle(OrderPaymentReminderService $reminder): int
    {
        $remindAfter = (int) $this->option('remind-after');
        $cancelAfter = (int) $this->option('cancel-after');

        $remindThreshold = now()->subHours($remindAfter);
        $cancelThreshold = now()->subHours($cancelAfter);

        $stats = ['reminded' => 0, 'cancelled' => 0, 'errors' => 0];

        Order::where('payment_method', 'bank_transfer')
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('created_at', '<', $cancelThreshold)
            ->with(['items', 'customer', 'coupon'])
            ->chunkById(100, function ($orders) use ($reminder, $cancelAfter, &$stats) {
                foreach ($orders as $order) {
                    try {
                        $reminder->cancelOrder(
                            order: $order,
                            reason: "超過 {$cancelAfter} 小時未完成銀行轉帳",
                            emailSubject: "【婕樂纖仙女館】訂單 {$order->order_number} 已自動取消",
                            emailBody: "您的訂單 {$order->order_number} 因未在期限內完成銀行轉帳，已自動取消。如仍要購買請重新下單，謝謝。",
                        );
                        $stats['cancelled']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('[bank-transfer:auto-cancel] failed', ['order' => $order->order_number, 'msg' => $e->getMessage()]);
                    }
                }
            });

        Order::where('payment_method', 'bank_transfer')
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('created_at', '<', $remindThreshold)
            ->where('created_at', '>=', $cancelThreshold)
            ->whereNull('confirmation_reminder_sent_at')
            ->with(['customer'])
            ->chunkById(100, function ($orders) use ($reminder, &$stats) {
                foreach ($orders as $order) {
                    try {
                        $totalStr = 'NT$' . number_format((int) $order->total);
                        $reminder->sendReminder(
                            order: $order,
                            emailSubject: "【婕樂纖仙女館】您的訂單 {$order->order_number} 尚未付款",
                            emailBody: "您的訂單 {$order->order_number}（{$totalStr}）已成立超過 24 小時但尚未完成轉帳。請於 24 小時內完成轉帳，否則訂單將自動取消。\n\n富邦銀行（012）\n帳號：82110000082812\n戶名：法芮可有限公司",
                            lineFlexBubble: $reminder->reminderFlexBankTransfer($order),
                        );
                        $stats['reminded']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('[bank-transfer:reminder] failed', ['order' => $order->order_number, 'msg' => $e->getMessage()]);
                    }
                }
            });

        $this->info("reminded {$stats['reminded']} · cancelled {$stats['cancelled']} · errors {$stats['errors']}");
        return self::SUCCESS;
    }
}
