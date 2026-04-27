<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 共用「待處理訂單」的提醒 + 自動取消邏輯，給兩個 command 用：
 *   - cod:auto-cancel-unconfirmed   (status=pending_confirmation, 48h)
 *   - bank-transfer:auto-cancel     (payment_method=bank_transfer + payment_status=unpaid, 48h)
 *
 * 24h 發提醒（idempotent，靠 confirmation_reminder_sent_at），48h 取消 +
 * 還原庫存 / coupon + 通知。
 */
class OrderPaymentReminderService
{
    public function __construct(private LineMessagingService $line) {}

    public function cancelOrder(Order $order, string $reason, string $emailSubject, string $emailBody): void
    {
        DB::transaction(function () use ($order, $reason) {
            foreach ($order->items as $item) {
                if (!$item->product_id) continue;
                $product = Product::lockForUpdate()->find($item->product_id);
                if (!$product) continue;
                if ($product->stock_quantity > 0 || $product->stock_status === 'outofstock') {
                    $product->increment('stock_quantity', (int) $item->quantity);
                    if ($product->stock_quantity > 0 && $product->stock_status === 'outofstock') {
                        $product->update(['stock_status' => 'instock']);
                    }
                }
            }

            if ($order->coupon_id) {
                $coupon = Coupon::lockForUpdate()->find($order->coupon_id);
                if ($coupon && $coupon->used_count > 0) {
                    $coupon->decrement('used_count');
                }
            }

            $order->update([
                'status' => 'cancelled',
                'note' => trim(($order->note ? $order->note . "\n" : '') . "[系統自動取消] {$reason}"),
            ]);
        });

        $this->sendEmail($order, $emailSubject, $emailBody);
        $this->sendLine(
            $order,
            "訂單 {$order->order_number} 已自動取消",
            $this->cancelledFlex($order, $emailBody)
        );
    }

    public function sendReminder(Order $order, string $emailSubject, string $emailBody, array $lineFlexBubble): void
    {
        if ($order->confirmation_reminder_sent_at) return;

        $this->sendEmail($order, $emailSubject, $emailBody);
        $this->sendLine($order, "訂單 {$order->order_number} 提醒", $lineFlexBubble);

        $order->update(['confirmation_reminder_sent_at' => now()]);
    }

    private function sendEmail(Order $order, string $subject, string $body): void
    {
        $email = optional($order->customer)->email;
        if (!$email || str_ends_with($email, '@line.user')) return;

        try {
            Mail::raw($body . "\n\n— 婕樂纖仙女館 FP", function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('[order.reminder] email failed', [
                'order' => $order->order_number, 'msg' => $e->getMessage(),
            ]);
        }
    }

    private function sendLine(Order $order, string $altText, array $flexBubble): void
    {
        if (!$this->line->isConfigured()) return;
        $userId = $order->line_user_id ?: optional($order->customer)->line_id;
        if (!$userId) return;
        $this->line->pushFlex($userId, $altText, $flexBubble);
    }

    private function cancelledFlex(Order $order, string $body): array
    {
        return [
            'type' => 'bubble',
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md',
                'contents' => [
                    ['type' => 'text', 'text' => '訂單已自動取消', 'weight' => 'bold', 'size' => 'lg', 'color' => '#9F6B3E'],
                    ['type' => 'text', 'text' => "訂單編號 {$order->order_number}", 'size' => 'sm', 'color' => '#888888'],
                    ['type' => 'separator'],
                    ['type' => 'text', 'text' => $body, 'wrap' => true, 'size' => 'sm'],
                ],
            ],
        ];
    }

    public function reminderFlexCod(Order $order): array
    {
        return [
            'type' => 'bubble',
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md',
                'contents' => [
                    ['type' => 'text', 'text' => '別忘了確認您的訂單', 'weight' => 'bold', 'size' => 'lg', 'color' => '#9F6B3E'],
                    ['type' => 'text', 'text' => "訂單 {$order->order_number}", 'size' => 'sm', 'color' => '#888888'],
                    ['type' => 'separator'],
                    ['type' => 'text', 'text' => '您下單已超過 24 小時，請點先前的「確認出貨」訊息按鈕完成下單。再過 24 小時未確認，訂單將自動取消。', 'wrap' => true, 'size' => 'sm'],
                ],
            ],
        ];
    }

    public function reminderFlexBankTransfer(Order $order): array
    {
        $totalStr = 'NT$' . number_format((int) $order->total);
        return [
            'type' => 'bubble',
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md',
                'contents' => [
                    ['type' => 'text', 'text' => '別忘了完成轉帳', 'weight' => 'bold', 'size' => 'lg', 'color' => '#9F6B3E'],
                    ['type' => 'text', 'text' => "訂單 {$order->order_number}（{$totalStr}）", 'size' => 'sm', 'color' => '#888888'],
                    ['type' => 'separator'],
                    ['type' => 'text', 'text' => '富邦銀行（012）', 'size' => 'sm'],
                    ['type' => 'text', 'text' => '帳號 82110000082812', 'size' => 'sm', 'weight' => 'bold', 'color' => '#9F6B3E'],
                    ['type' => 'separator'],
                    ['type' => 'text', 'text' => '您下單已超過 24 小時但尚未完成轉帳。請於 24 小時內完成，否則訂單將自動取消。', 'wrap' => true, 'size' => 'sm'],
                ],
            ],
        ];
    }
}
