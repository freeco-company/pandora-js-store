<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Services\LineMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * COD pending_confirmation 訂單超過 48 小時未確認 → 自動取消。
 *
 * 為什麼要這個 job 而不是「永遠卡著」：
 *   - 卡著的訂單會 hold 庫存（OrderController::store 已扣庫存）
 *   - 卡著的訂單會 hold coupon 使用次數
 *   - 卡著的訂單會被誤認為待出貨，造成後台噪音
 *
 * 還原動作：
 *   - 訂單 status → cancelled
 *   - 商品庫存歸還（按 order_items.quantity）
 *   - coupon used_count -1（若有用 coupon）
 *   - 若有 line_user_id → push 取消通知
 *   - 若有 email（非 placeholder）→ 寄取消通知
 *
 * 跑頻率：每 30 分鐘（夠細，48h 邊界誤差最多 30 分鐘可接受）
 */
class AutoCancelUnconfirmedOrdersCmd extends Command
{
    protected $signature = 'cod:auto-cancel-unconfirmed {--hours=48 : 過期門檻}';
    protected $description = 'Cancel COD orders that have been pending LINE confirmation beyond the threshold';

    public function handle(LineMessagingService $line): int
    {
        $hours = (int) $this->option('hours');
        $threshold = now()->subHours($hours);

        $orders = Order::where('status', 'pending_confirmation')
            ->where('created_at', '<', $threshold)
            ->with(['items', 'customer', 'coupon'])
            ->get();

        $cancelled = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                DB::transaction(function () use ($order, $hours) {
                    // 還原庫存（與 OrderController::store 的扣庫存對稱）
                    foreach ($order->items as $item) {
                        if (!$item->product_id) continue;
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if (!$product) continue;
                        // 0 = 不限量，跳過；其他則加回去
                        if ($product->stock_quantity > 0 || $product->stock_status === 'outofstock') {
                            $product->increment('stock_quantity', (int) $item->quantity);
                            if ($product->stock_quantity > 0 && $product->stock_status === 'outofstock') {
                                $product->update(['stock_status' => 'instock']);
                            }
                        }
                    }

                    // 還原 coupon 使用次數
                    if ($order->coupon_id) {
                        $coupon = Coupon::lockForUpdate()->find($order->coupon_id);
                        if ($coupon && $coupon->used_count > 0) {
                            $coupon->decrement('used_count');
                        }
                    }

                    $order->update([
                        'status' => 'cancelled',
                        'note' => trim(($order->note ? $order->note . "\n" : '') . "[系統自動取消] 超過 {$hours} 小時未在 LINE 確認出貨"),
                    ]);
                });

                $this->notifyCustomer($order, $line);
                $cancelled++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('[cod:auto-cancel] failed', [
                    'order' => $order->order_number,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        $this->info("cancelled {$cancelled} · errors {$errors}");
        return self::SUCCESS;
    }

    private function notifyCustomer(Order $order, LineMessagingService $line): void
    {
        $msg = "您的訂單 {$order->order_number} 因未在期限內於 LINE 完成確認，已自動取消。如仍要購買請重新下單，謝謝 🙏";

        // LINE 通知（若有綁 line_user_id）
        if ($order->line_user_id && $line->isConfigured()) {
            $line->pushFlex(
                $order->line_user_id,
                "訂單 {$order->order_number} 已自動取消",
                $this->cancelledFlex($order)
            );
        }

        // Email 通知
        $email = optional($order->customer)->email;
        if ($email && !str_ends_with($email, '@line.user')) {
            try {
                Mail::raw($msg . "\n\n— 婕樂纖仙女館 FP", function ($m) use ($email, $order) {
                    $m->to($email)->subject("【婕樂纖仙女館】訂單 {$order->order_number} 已自動取消");
                });
            } catch (\Throwable $e) {
                Log::warning('[cod:auto-cancel] email failed', ['order' => $order->order_number, 'msg' => $e->getMessage()]);
            }
        }
    }

    private function cancelledFlex(Order $order): array
    {
        return [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'md',
                'contents' => [
                    ['type' => 'text', 'text' => '訂單已自動取消', 'weight' => 'bold', 'size' => 'lg', 'color' => '#9F6B3E'],
                    ['type' => 'text', 'text' => "訂單編號 {$order->order_number}", 'size' => 'sm', 'color' => '#888888'],
                    ['type' => 'separator'],
                    [
                        'type' => 'text',
                        'text' => '您的訂單因未在 48 小時內完成確認，已自動取消。如仍要購買請重新下單，謝謝 🙏',
                        'wrap' => true,
                        'size' => 'sm',
                    ],
                ],
            ],
        ];
    }
}
