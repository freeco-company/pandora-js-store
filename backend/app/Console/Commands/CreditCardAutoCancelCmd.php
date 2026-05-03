<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderPaymentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 信用卡（ECPay credit card）訂單：多段提醒 / 7 天未付款自動取消。
 *
 * 偵測條件：
 *   - payment_method = 'ecpay_credit'
 *   - payment_status = 'unpaid'
 *   - status NOT IN ('cancelled', 'refunded')
 *
 * 階段（OrderPaymentReminderService::STAGES_CC）：
 *   - 1h / 6h / 24h / 72h / 144h 寄提醒（email + LINE，含補刷連結）
 *   - 168h（7d）未付款 → 取消、還原庫存 / coupon、通知
 *
 * 補刷機制：使用者透過 /order-lookup?order=PD... 重新刷卡，
 * EcpayService::createPayment() 會自動在 MerchantTradeNo 加 R1/R2/...
 * suffix 避開 ECPay 10300028「訂單編號重覆」錯誤。
 */
class CreditCardAutoCancelCmd extends Command
{
    protected $signature = 'cc:auto-cancel-unpaid';
    protected $description = 'Multi-stage reminders + 7d auto-cancel for credit card orders that never completed payment';

    public function handle(OrderPaymentReminderService $reminder): int
    {
        $cancelHours = OrderPaymentReminderService::CANCEL_HOURS_CC;
        $cancelThreshold = now()->subHours($cancelHours);

        $stats = ['reminded' => 0, 'cancelled' => 0, 'errors' => 0];

        Order::where('payment_method', 'ecpay_credit')
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('created_at', '<', $cancelThreshold)
            ->with(['items', 'customer', 'coupon'])
            ->chunkById(100, function ($orders) use ($reminder, $cancelHours, &$stats) {
                foreach ($orders as $order) {
                    try {
                        $reminder->cancelOrder(
                            order: $order,
                            reason: "超過 {$cancelHours} 小時未完成信用卡付款",
                            emailSubject: "【婕樂纖仙女館】訂單 {$order->order_number} 已自動取消",
                            emailBody: "您的訂單 {$order->order_number} 因未在期限內完成信用卡付款，已自動取消。如仍要購買請重新下單，謝謝。",
                        );
                        $stats['cancelled']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('[cc:auto-cancel] failed', ['order' => $order->order_number, 'msg' => $e->getMessage()]);
                    }
                }
            });

        Order::where('payment_method', 'ecpay_credit')
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('created_at', '>=', $cancelThreshold)
            ->with(['customer'])
            ->chunkById(100, function ($orders) use ($reminder, &$stats) {
                foreach ($orders as $order) {
                    try {
                        if ($reminder->processReminderStages($order, 'cc')) {
                            $stats['reminded']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('[cc:reminder] failed', ['order' => $order->order_number, 'msg' => $e->getMessage()]);
                    }
                }
            });

        $this->info("reminded {$stats['reminded']} · cancelled {$stats['cancelled']} · errors {$stats['errors']}");
        return self::SUCCESS;
    }
}
