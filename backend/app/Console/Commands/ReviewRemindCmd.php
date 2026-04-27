<?php

namespace App\Console\Commands;

use App\Mail\ReviewReminderMail;
use App\Models\Order;
use App\Models\Review;
use App\Services\LineMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Two-phase review reminder lifecycle (no auto-create reviews):
 *
 * Phase 1 — First reminder (7 days after order completed):
 *   email + LINE push（若有 customer.line_id）
 *   標記 review_reminder_sent_at
 *
 * Phase 2 — Final reminder (14 days after order completed):
 *   只給已送過 phase 1、仍未評論的訂單
 *   email + LINE push, 文案急迫一點（最後提醒）
 *   標記 review_reminder_2_sent_at
 *
 * **Auto-create 5-star reviews 已停用**：未經客人同意自動產生 verified-purchase
 * 評論違反公平交易法。只有真實客人評論才能標 verified。
 *
 * Runs daily at 10:00 Asia/Taipei via scheduler.
 */
class ReviewRemindCmd extends Command
{
    protected $signature = 'review:remind';
    protected $description = 'Send 2-phase review reminders (7d first, 14d final) via email + LINE for completed orders';

    private const FIRST_REMIND_AFTER_DAYS = 7;
    private const FINAL_REMIND_AFTER_DAYS = 14;
    /** Don't chase orders older than this — assume customer never coming back */
    private const STALE_AFTER_DAYS = 60;

    public function handle(LineMessagingService $line): int
    {
        $first = $this->sendFirstReminders($line);
        $final = $this->sendFinalReminders($line);

        $this->info("first reminders: {$first} · final reminders: {$final}");
        return self::SUCCESS;
    }

    /**
     * Phase 1 — 7d first reminder.
     */
    private function sendFirstReminders(LineMessagingService $line): int
    {
        $sent = 0;

        $orders = Order::where('status', 'completed')
            ->whereNotNull('customer_id')
            ->whereNull('review_reminder_sent_at')
            ->where('updated_at', '<', now()->subDays(self::FIRST_REMIND_AFTER_DAYS))
            ->where('updated_at', '>', now()->subDays(self::STALE_AFTER_DAYS))
            ->with(['items.product', 'customer'])
            ->get();

        foreach ($orders as $order) {
            $unreviewedNames = $this->unreviewedProductNames($order);
            if (empty($unreviewedNames)) {
                // 已全評過 → 跳過下次也不再掃
                $order->update(['review_reminder_sent_at' => now()]);
                continue;
            }
            if ($this->notifyOrder($order, $unreviewedNames, false, $line)) {
                $order->update(['review_reminder_sent_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Phase 2 — 14d final reminder（只給已送 phase 1 仍未評的）。
     */
    private function sendFinalReminders(LineMessagingService $line): int
    {
        $sent = 0;

        $orders = Order::where('status', 'completed')
            ->whereNotNull('customer_id')
            ->whereNotNull('review_reminder_sent_at')   // 必須先送過第一輪
            ->whereNull('review_reminder_2_sent_at')    // 第二輪沒送過
            ->where('updated_at', '<', now()->subDays(self::FINAL_REMIND_AFTER_DAYS))
            ->where('updated_at', '>', now()->subDays(self::STALE_AFTER_DAYS))
            ->with(['items.product', 'customer'])
            ->get();

        foreach ($orders as $order) {
            $unreviewedNames = $this->unreviewedProductNames($order);
            if (empty($unreviewedNames)) {
                $order->update(['review_reminder_2_sent_at' => now()]);
                continue;
            }
            if ($this->notifyOrder($order, $unreviewedNames, true, $line)) {
                $order->update(['review_reminder_2_sent_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return string[] 訂單裡客人還沒寫過評論的商品名稱
     */
    private function unreviewedProductNames(Order $order): array
    {
        $names = [];
        foreach ($order->items as $item) {
            if (!$item->product) continue;
            $exists = Review::where('customer_id', $order->customer_id)
                ->where('product_id', $item->product_id)
                ->where('order_id', $order->id)
                ->exists();
            if (!$exists) $names[] = $item->product->name;
        }
        return $names;
    }

    /**
     * 寄 email + push LINE。任一管道有送出就算成功（讓 phase mark 寫入避免重複嘗試）。
     */
    private function notifyOrder(Order $order, array $unreviewedNames, bool $isFinal, LineMessagingService $line): bool
    {
        $emailOk = $this->sendEmail($order, $unreviewedNames, $isFinal);
        $lineOk = $this->pushLine($order, $unreviewedNames, $isFinal, $line);
        return $emailOk || $lineOk;
    }

    private function sendEmail(Order $order, array $unreviewedNames, bool $isFinal): bool
    {
        $email = optional($order->customer)->email;
        if (!$email || str_ends_with($email, '@line.user')) return false;
        try {
            Mail::to($email)->send(new ReviewReminderMail($order, $unreviewedNames, $isFinal));
            return true;
        } catch (\Throwable $e) {
            Log::warning('[review:remind] email failed', [
                'order' => $order->order_number, 'is_final' => $isFinal, 'msg' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function pushLine(Order $order, array $unreviewedNames, bool $isFinal, LineMessagingService $line): bool
    {
        if (!$line->isConfigured()) return false;
        $userId = optional($order->customer)->line_id;
        if (!$userId) return false;

        $altText = $isFinal
            ? "最後提醒：訂單 {$order->order_number} 待您評論"
            : "您的訂單 {$order->order_number} 商品已到貨，分享心得吧";

        return $line->pushFlex($userId, $altText, $this->reviewFlex($order, $unreviewedNames, $isFinal));
    }

    private function reviewFlex(Order $order, array $unreviewedNames, bool $isFinal): array
    {
        $title = $isFinal ? '最後提醒 · 分享您的使用心得' : '用得還滿意嗎？';
        $subtitle = $isFinal
            ? '訂單已到貨超過 14 天，這是最後一次提醒'
            : '花 30 秒留下評價，幫助其他仙女選購';
        $reviewUrl = rtrim(config('services.frontend.url', 'https://pandora.js-store.com.tw'), '/')
            . '/account?tab=reviews';

        // 商品列表（最多 4 個避免 Flex bubble 太長）
        $itemContents = [];
        foreach (array_slice($unreviewedNames, 0, 4) as $name) {
            $itemContents[] = [
                'type' => 'text',
                'text' => '・' . mb_substr($name, 0, 28),
                'size' => 'sm',
                'color' => '#1f1a15',
                'wrap' => true,
            ];
        }
        if (count($unreviewedNames) > 4) {
            $itemContents[] = [
                'type' => 'text',
                'text' => '... 共 ' . count($unreviewedNames) . ' 件商品',
                'size' => 'xs',
                'color' => '#888888',
            ];
        }

        return [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => $isFinal ? '#85572F' : '#9F6B3E',
                'paddingAll' => '16px',
                'contents' => [
                    ['type' => 'text', 'text' => '婕樂纖仙女館', 'color' => '#FFFFFF', 'size' => 'xs', 'weight' => 'bold'],
                    ['type' => 'text', 'text' => $title, 'color' => '#FFFFFF', 'size' => 'lg', 'weight' => 'bold', 'margin' => 'sm', 'wrap' => true],
                    ['type' => 'text', 'text' => $subtitle, 'color' => '#FFFFFF', 'size' => 'xs', 'margin' => 'xs', 'wrap' => true],
                ],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'md',
                'contents' => array_merge(
                    [
                        ['type' => 'text', 'text' => "訂單 {$order->order_number}", 'size' => 'xs', 'color' => '#888888'],
                        ['type' => 'text', 'text' => '等待您評價的商品：', 'size' => 'sm', 'color' => '#7a5836', 'weight' => 'bold'],
                    ],
                    $itemContents,
                ),
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [[
                    'type' => 'button',
                    'style' => 'primary',
                    'color' => '#9F6B3E',
                    'action' => ['type' => 'uri', 'label' => '立即評價', 'uri' => $reviewUrl],
                ]],
            ],
        ];
    }
}
