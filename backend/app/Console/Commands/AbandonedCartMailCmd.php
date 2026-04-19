<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartMail;
use App\Models\Order;
use App\Services\LineMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send one recovery reminder per abandoned order — identified as
 *  pending + unpaid + > 24h old + not yet reminded.
 *
 * Two channels fire as a unit, recorded under one timestamp
 * (`abandoned_reminder_sent_at`) so the order is not retried:
 *   - Email (always, when customer has a non-placeholder email)
 *   - LINE push (when customer has a line_id and Messaging token is set)
 *
 * Real-world win: LINE push open-rate >> email for TW commerce. Same DB
 * row, two outreach channels.
 *
 * Runs every 3h via scheduler.
 */
class AbandonedCartMailCmd extends Command
{
    protected $signature = 'cart:abandoned-mail';
    protected $description = 'Send abandoned-cart recovery via email + LINE push';

    public function handle(LineMessagingService $line): int
    {
        $emailSent = 0;
        $lineSent = 0;
        $errors = 0;

        $orders = Order::where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->whereNull('abandoned_reminder_sent_at')
            ->where('created_at', '<', now()->subHours(24))
            ->where('created_at', '>', now()->subDays(7))    // don't spam ancient orders
            ->with(['items', 'customer'])
            ->get();

        foreach ($orders as $order) {
            $customer = $order->customer;
            if (! $customer) continue;

            $touched = false;

            // Email — skip the @line.user placeholder we synth for OAuth-only accounts
            $email = $customer->email;
            $isPlaceholder = $email && str_ends_with($email, '@line.user');
            if ($email && ! $isPlaceholder) {
                try {
                    Mail::to($email)->send(new AbandonedCartMail($order));
                    $emailSent++;
                    $touched = true;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('[cart:abandoned-mail] email failed', [
                        'order' => $order->order_number, 'msg' => $e->getMessage(),
                    ]);
                }
            }

            // LINE push — service no-ops if not configured. Login + Messaging
            // channels must be in the same provider for line_id to be valid here.
            if ($customer->line_id && $line->isConfigured()) {
                $lookupUrl = rtrim(config('services.ecpay.frontend_url', 'https://pandora.js-store.com.tw'), '/')
                    . '/order-lookup?order=' . urlencode($order->order_number);

                $text = "婕樂仙女～您的訂單 {$order->order_number} 還沒付款喔！\n"
                      . "金額 NT$" . number_format((int) $order->total) . "，活動有限時，補完款就能準備出貨囉～";

                if ($line->pushText($customer->line_id, $text, [
                    ['label' => '查看訂單', 'uri' => $lookupUrl],
                ])) {
                    $lineSent++;
                    $touched = true;
                }
            }

            if ($touched) {
                $order->update(['abandoned_reminder_sent_at' => now()]);
            }
        }

        $this->info("email {$emailSent} · line {$lineSent} · errors {$errors}");
        return self::SUCCESS;
    }
}
