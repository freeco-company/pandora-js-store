<?php

namespace App\Console\Commands;

use App\Mail\BackInStockMail;
use App\Models\Product;
use App\Models\StockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Scan stock_notifications for un-notified subscribers whose product is now in stock.
 * Sends the back-in-stock mail and marks the row notified.
 *
 * Runs hourly via scheduler.
 */
class NotifyBackInStock extends Command
{
    protected $signature = 'stock:notify';
    protected $description = 'Mail subscribers when their product is back in stock';

    public function handle(): int
    {
        $sent = 0;
        $errors = 0;

        $pending = StockNotification::whereNull('notified_at')
            ->with('product')
            ->get();

        foreach ($pending as $sub) {
            $p = $sub->product;
            if (! $p || $p->stock_status !== 'instock' || ! $p->is_active) continue;

            try {
                Mail::to($sub->email)->send(new BackInStockMail($p));
                $sub->update(['notified_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('[stock:notify] send failed', [
                    'email' => $sub->email, 'product_id' => $p->id, 'msg' => $e->getMessage(),
                ]);
            }
        }

        $this->info("sent {$sent} · errors {$errors}");
        return self::SUCCESS;
    }
}
