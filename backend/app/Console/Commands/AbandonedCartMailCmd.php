<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartMail;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send one recovery mail per abandoned order — identified as
 *  pending + unpaid + > 24h old + not yet reminded.
 *
 * Tracks `abandoned_reminder_sent_at` so we don't spam.
 *
 * Runs every 3h via scheduler.
 */
class AbandonedCartMailCmd extends Command
{
    protected $signature = 'cart:abandoned-mail';
    protected $description = 'Email a recovery reminder for unpaid orders older than 24h';

    public function handle(): int
    {
        $sent = 0;
        $errors = 0;

        $orders = Order::where('status', 'pending')
            ->where('payment_status', 'unpaid')
            ->whereNull('abandoned_reminder_sent_at')
            ->where('created_at', '<', now()->subHours(24))
            ->where('created_at', '>', now()->subDays(7))    // don't spam ancient orders
            ->with(['items', 'customer'])
            ->get();

        foreach ($orders as $order) {
            $email = $order->customer?->email;
            if (! $email) continue;

            try {
                Mail::to($email)->send(new AbandonedCartMail($order));
                $order->update(['abandoned_reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('[cart:abandoned-mail] failed', [
                    'order' => $order->order_number, 'msg' => $e->getMessage(),
                ]);
            }
        }

        $this->info("sent {$sent} · errors {$errors}");
        return self::SUCCESS;
    }
}
