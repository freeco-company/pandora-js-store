<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 「待處理訂單」多段提醒 + 自動取消邏輯。三個 command 共用：
 *   - cod:auto-cancel-unconfirmed   (status=pending_confirmation)         3h / 24h → 48h cancel
 *   - bank-transfer:auto-cancel     (bank_transfer + unpaid)              3h / 24h → 48h cancel
 *   - cc:auto-cancel-unpaid         (ecpay_credit + unpaid)               1h / 6h / 24h / 72h / 144h → 168h cancel
 *
 * 階段 idempotency 用 `confirmation_reminder_stage`（int，0 = 沒送過）。
 * 每次 schedule 跑時，計算 order 年齡屬於第幾段，若 dueStage > 已送 stage 就送下一段。
 *
 * 所有 email 額外 BCC contact@freeco.cc 方便集團內部追蹤是否寄出。
 */
class OrderPaymentReminderService
{
    private const BCC_INTERNAL = 'contact@freeco.cc';

    public function __construct(private LineMessagingService $line) {}

    /** 信用卡（ecpay_credit + unpaid）— 7 天取消，5 段提醒 */
    public const STAGES_CC = [
        ['hours' => 1,   'label' => '1 小時'],
        ['hours' => 6,   'label' => '6 小時'],
        ['hours' => 24,  'label' => '1 天'],
        ['hours' => 72,  'label' => '3 天'],
        ['hours' => 144, 'label' => '6 天'],
    ];
    public const CANCEL_HOURS_CC = 168;

    /** 銀行轉帳（bank_transfer + unpaid）— 48h 取消，2 段提醒 */
    public const STAGES_BT = [
        ['hours' => 3,  'label' => '3 小時'],
        ['hours' => 24, 'label' => '1 天'],
    ];
    public const CANCEL_HOURS_BT = 48;

    /** 貨到付款（pending_confirmation）— 48h 取消，2 段提醒 */
    public const STAGES_COD = [
        ['hours' => 3,  'label' => '3 小時'],
        ['hours' => 24, 'label' => '1 天'],
    ];
    public const CANCEL_HOURS_COD = 48;

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

    /**
     * 多段提醒處理：依訂單年齡決定該到第幾段，已送過就跳過。
     * Returns true if a reminder was actually sent.
     *
     * @param  'cc'|'bt'|'cod'  $type
     */
    public function processReminderStages(Order $order, string $type): bool
    {
        $stages = match ($type) {
            'cc'  => self::STAGES_CC,
            'bt'  => self::STAGES_BT,
            'cod' => self::STAGES_COD,
            default => throw new \InvalidArgumentException("Unknown reminder type: {$type}"),
        };

        $hoursOld = $order->created_at ? $order->created_at->diffInHours(now()) : 0;
        $currentStage = (int) ($order->confirmation_reminder_stage ?? 0);

        $dueStage = 0;
        foreach ($stages as $i => $cfg) {
            if ($hoursOld >= $cfg['hours']) {
                $dueStage = $i + 1;
            }
        }

        if ($dueStage <= $currentStage) {
            return false;
        }

        [$subject, $body, $flex] = $this->buildStageContent($order, $type, $dueStage);

        $this->sendEmail($order, $subject, $body);
        if ($flex !== null) {
            $this->sendLine($order, "訂單 {$order->order_number} 提醒（第 {$dueStage} 段）", $flex);
        }

        $order->update([
            'confirmation_reminder_stage' => $dueStage,
            'confirmation_reminder_sent_at' => now(),
        ]);
        return true;
    }

    /**
     * @return array{0:string,1:string,2:?array} [subject, body, lineFlexBubble]
     */
    private function buildStageContent(Order $order, string $type, int $stage): array
    {
        $orderNo = $order->order_number;
        $totalStr = 'NT$' . number_format((int) $order->total);
        $repayUrl = rtrim((string) config('services.ecpay.frontend_url', config('app.url')), '/')
            . '/order-lookup?order=' . urlencode($orderNo);

        if ($type === 'cc') {
            return $this->ccStage($order, $stage, $orderNo, $totalStr, $repayUrl);
        }
        if ($type === 'bt') {
            return $this->btStage($order, $stage, $orderNo, $totalStr);
        }
        return $this->codStage($order, $stage, $orderNo);
    }

    /** 信用卡 5 段 */
    private function ccStage(Order $order, int $stage, string $orderNo, string $totalStr, string $repayUrl): array
    {
        $tail = match ($stage) {
            1 => "您於剛才下單但似乎沒有完成刷卡。庫存為您保留中，點下方連結即可重新付款（同一張訂單可重複嘗試）。",
            2 => "您的訂單刷卡尚未完成，再次提醒您完成付款，庫存仍為您保留。",
            3 => "您下單已超過 24 小時，刷卡尚未完成。庫存為您保留中，請盡快完成付款。",
            4 => "您下單已超過 3 天，請於 4 天內完成付款，否則訂單將自動取消。",
            5 => "最後提醒：您下單已超過 6 天，再過 1 天訂單將自動取消並釋放庫存。",
            default => "請盡快完成付款。",
        };
        $subject = "【婕樂纖仙女館】訂單 {$orderNo} 尚未完成付款（{$totalStr}）";
        $body = "{$tail}\n\n訂單編號：{$orderNo}\n金額：{$totalStr}\n\n重新付款：{$repayUrl}\n\n若已完成付款請忽略本信。如有疑問請回覆此信或聯絡客服。";

        $flex = [
            'type' => 'bubble',
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md',
                'contents' => [
                    ['type' => 'text', 'text' => '訂單尚未完成付款', 'weight' => 'bold', 'size' => 'lg', 'color' => '#9F6B3E'],
                    ['type' => 'text', 'text' => "{$orderNo}（{$totalStr}）", 'size' => 'sm', 'color' => '#888888'],
                    ['type' => 'separator'],
                    ['type' => 'text', 'text' => $tail, 'wrap' => true, 'size' => 'sm'],
                    ['type' => 'separator'],
                    ['type' => 'text', 'text' => '點此重新付款 →', 'size' => 'sm', 'color' => '#9F6B3E', 'weight' => 'bold', 'action' => ['type' => 'uri', 'uri' => $repayUrl]],
                ],
            ],
        ];
        return [$subject, $body, $flex];
    }

    /** 銀行轉帳 2 段 */
    private function btStage(Order $order, int $stage, string $orderNo, string $totalStr): array
    {
        $bank = "富邦銀行（012）\n帳號：82110000082812\n戶名：法芮可有限公司";
        $tail = match ($stage) {
            1 => "您的訂單已成立，請盡快完成銀行轉帳，庫存為您保留中。",
            2 => "您下單已超過 24 小時但尚未完成轉帳。請於 24 小時內完成，否則訂單將自動取消。",
            default => "請盡快完成轉帳。",
        };
        $subject = "【婕樂纖仙女館】訂單 {$orderNo} 尚未付款（{$totalStr}）";
        $body = "{$tail}\n\n訂單編號：{$orderNo}\n金額：{$totalStr}\n\n{$bank}\n\n完成轉帳後請於『訂單查詢』頁面回報帳號末五碼。如有疑問請回覆此信。";

        $flex = $this->reminderFlexBankTransfer($order);
        return [$subject, $body, $flex];
    }

    /** COD pending_confirmation 2 段 */
    private function codStage(Order $order, int $stage, string $orderNo): array
    {
        $tail = match ($stage) {
            1 => "您的訂單已成立，請至 LINE 點先前訊息的「確認出貨」按鈕完成下單。",
            2 => "您下單已超過 24 小時尚未確認，再過 24 小時未確認訂單將自動取消。",
            default => "請至 LINE 完成確認。",
        };
        $subject = "【婕樂纖仙女館】訂單 {$orderNo} 還沒確認喔！";
        $body = "{$tail}\n\n訂單編號：{$orderNo}\n\n若已完成確認請忽略本信。";

        $flex = $this->reminderFlexCod($order);
        return [$subject, $body, $flex];
    }

    private function sendEmail(Order $order, string $subject, string $body): void
    {
        $email = optional($order->customer)->email;
        $hasCustomerEmail = $email && !str_ends_with($email, '@line.user');

        // 即便沒有客戶 email，也要寄到內部信箱 contact@freeco.cc 留紀錄
        try {
            Mail::raw($body . "\n\n— 婕樂纖仙女館 FP", function ($m) use ($email, $subject, $hasCustomerEmail) {
                if ($hasCustomerEmail) {
                    $m->to($email);
                } else {
                    $m->to(self::BCC_INTERNAL);
                }
                if ($hasCustomerEmail) {
                    $m->bcc(self::BCC_INTERNAL);
                }
                $m->subject($subject);
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
                    ['type' => 'text', 'text' => '請點先前訊息的「確認出貨」按鈕完成下單。', 'wrap' => true, 'size' => 'sm'],
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
                    ['type' => 'text', 'text' => '請盡快完成轉帳，避免訂單因逾時取消。', 'wrap' => true, 'size' => 'sm'],
                ],
            ],
        ];
    }
}
