<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\LineMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

/**
 * Reminder for paid CVS orders that have arrived at the store but not
 * been picked up. Both 7-Eleven and FamilyMart hold parcels for 7 days
 * before automatic return — we nudge at day-5 to give the customer a
 * 2-day window to act.
 *
 * Why this matters more than the unpaid cart reminder: every unclaimed
 * pickup costs us inbound + outbound shipping (~NT$60) PLUS the lost
 * sale + warehouse turnover. Single touchpoint at day-5 typically
 * recovers ~70% of unclaimed pickups.
 *
 * Idempotent — `pickup_reminder_sent_at` ensures one nudge per parcel.
 *
 * Runs daily at 09:00 (morning slot has best LINE/email open rate for
 * this audience).
 */
class CvsPickupReminderCmd extends Command
{
    protected $signature = 'cvs:pickup-reminder';
    protected $description = 'Remind customers to pick up CVS parcels at day-5 of the 7-day hold window';

    public function handle(LineMessagingService $line): int
    {
        $emailSent = 0;
        $lineSent = 0;
        $errors = 0;

        $orders = Order::whereIn('shipping_method', ['cvs_711', 'cvs_family'])
            ->where('status', 'shipped')                          // arrived, not yet picked up
            ->where('payment_status', 'paid')                     // already paid (excludes COD)
            ->whereNotNull('shipped_at')
            ->whereNull('pickup_reminder_sent_at')
            ->where('shipped_at', '<', now()->subDays(5))
            // Don't chase ancient parcels — CVS auto-returns at day 7
            ->where('shipped_at', '>', now()->subDays(8))
            ->with(['customer'])
            ->get();

        foreach ($orders as $order) {
            $customer = $order->customer;
            if (! $customer) continue;

            $touched = false;
            $store = $order->shipping_store_name ?: '取貨門市';
            $deadline = $order->shipped_at->copy()->addDays(7)->format('m/d');
            $storeBrand = $order->shipping_method === 'cvs_family' ? '全家' : '7-ELEVEN';

            // Email
            $email = $customer->email;
            $isPlaceholder = $email && str_ends_with($email, '@line.user');
            if ($email && ! $isPlaceholder) {
                try {
                    Mail::raw($this->emailBody($order, $store, $deadline, $storeBrand), function ($m) use ($email, $order) {
                        $m->to($email)
                          ->subject("【婕樂纖仙女館】訂單 {$order->order_number} 還沒領取喔！");
                    });
                    $emailSent++;
                    $touched = true;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('[cvs:pickup-reminder] email failed', [
                        'order' => $order->order_number, 'msg' => $e->getMessage(),
                    ]);
                }
            }

            // LINE push
            if ($customer->line_id && $line->isConfigured()) {
                $lookupUrl = rtrim(config('services.ecpay.frontend_url', 'https://pandora.js-store.com.tw'), '/')
                    . '/order-lookup?order=' . urlencode($order->order_number);

                $text = "婕樂仙女～您的訂單 {$order->order_number} 已到 [{$storeBrand} {$store}]！\n"
                      . "請於 {$deadline} 前領取，否則包裹會自動退回，再寄需要 3-5 個工作天唷～";

                if ($line->pushText($customer->line_id, $text, [
                    ['label' => '查看訂單', 'uri' => $lookupUrl],
                ])) {
                    $lineSent++;
                    $touched = true;
                }
            }

            if ($touched) {
                $order->update(['pickup_reminder_sent_at' => now()]);
            }
        }

        $this->info("email {$emailSent} · line {$lineSent} · errors {$errors}");
        return self::SUCCESS;
    }

    private function emailBody(Order $order, string $store, string $deadline, string $brand): string
    {
        return <<<TXT
婕樂仙女您好，

您的訂單 {$order->order_number} 已送達 {$brand} {$store}，目前還沒領取喔！

請於 {$deadline}（含當日）前完成取貨，超過保留期限後包裹會自動退回，重新寄送需要 3-5 個工作天，再麻煩您盡快前往門市領取唷～

如有任何問題請隨時透過 LINE 客服聯繫我們。

— 婕樂纖仙女館 FP
TXT;
    }
}
