<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ReviewController;
use App\Mail\ReviewReminderMail;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Two-phase review lifecycle:
 *
 * Phase 1 — Reminder (7 days after order completed):
 *   Send a friendly email asking the customer to leave a review.
 *   Tracks `reviews.auto_review_reminder_sent_at` via a marker on
 *   the order level (we use a dedicated column on orders table).
 *
 * Phase 2 — Auto-review (14 days after order completed):
 *   If the customer still hasn't reviewed, create a 5-star review
 *   with no content on their behalf. Marked as verified purchase.
 *
 * Runs daily at 10:00 Asia/Taipei via scheduler.
 */
class ReviewRemindCmd extends Command
{
    protected $signature = 'review:remind';
    protected $description = 'Send review reminders (7d) and auto-create reviews (14d) for completed orders';

    /** Days after completion to send reminder email */
    private const REMIND_AFTER_DAYS = 7;

    /** Days after completion to auto-create review */
    private const AUTO_REVIEW_AFTER_DAYS = 14;

    public function handle(): int
    {
        $reminded = $this->sendReminders();

        // Phase 2 (auto-create 5-star reviews) disabled — creating verified-
        // purchase reviews without customer consent violates Taiwan Fair Trade
        // Act (公平交易法). Only real customer reviews should be marked as
        // verified purchases.
        $autoCreated = 0;

        $this->info("reminders sent: {$reminded} · auto-reviews created: {$autoCreated}");
        return self::SUCCESS;
    }

    /**
     * Phase 1: Send reminder emails for orders completed 7+ days ago
     * that haven't been reminded yet and still have un-reviewed products.
     */
    private function sendReminders(): int
    {
        $sent = 0;

        $orders = Order::where('status', 'completed')
            ->whereNotNull('customer_id')
            ->whereNull('review_reminder_sent_at')
            ->where('updated_at', '<', now()->subDays(self::REMIND_AFTER_DAYS))
            ->where('updated_at', '>', now()->subDays(60)) // don't remind very old orders
            ->with(['items.product', 'customer'])
            ->get();

        foreach ($orders as $order) {
            $email = $order->customer?->email;
            if (! $email) continue;

            // Find products not yet reviewed by this customer for this order
            $unreviewedNames = [];
            foreach ($order->items as $item) {
                if (! $item->product) continue;
                $alreadyReviewed = Review::where('customer_id', $order->customer_id)
                    ->where('product_id', $item->product_id)
                    ->where('order_id', $order->id)
                    ->exists();
                if (! $alreadyReviewed) {
                    $unreviewedNames[] = $item->product->name;
                }
            }

            if (empty($unreviewedNames)) {
                // All reviewed already — mark as reminded so we skip next time
                $order->update(['review_reminder_sent_at' => now()]);
                continue;
            }

            try {
                Mail::to($email)->send(new ReviewReminderMail($order, $unreviewedNames));
                $order->update(['review_reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('[review:remind] mail failed', [
                    'order' => $order->order_number,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Phase 2: Auto-create 5-star reviews for orders completed 14+ days ago
     * where the customer hasn't reviewed yet (even after reminder).
     */
    private function autoCreateReviews(): int
    {
        $created = 0;

        $orders = Order::where('status', 'completed')
            ->whereNotNull('customer_id')
            ->where('updated_at', '<', now()->subDays(self::AUTO_REVIEW_AFTER_DAYS))
            ->where('updated_at', '>', now()->subDays(90)) // cap at 90 days
            ->with(['items.product', 'customer'])
            ->get();

        foreach ($orders as $order) {
            if (! $order->customer) continue;

            foreach ($order->items as $item) {
                if (! $item->product) continue;

                $alreadyReviewed = Review::where('customer_id', $order->customer_id)
                    ->where('product_id', $item->product_id)
                    ->where('order_id', $order->id)
                    ->exists();

                if ($alreadyReviewed) continue;

                $maskedName = ReviewController::maskName(
                    $order->customer->name,
                    $order->customer->email,
                );

                Review::create([
                    'product_id' => $item->product_id,
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'rating' => 5,
                    'content' => null,
                    'reviewer_name' => $maskedName,
                    'is_verified_purchase' => true,
                    'is_seeded' => false,
                    'is_visible' => true,
                ]);

                // Bust review cache for this product
                Cache::forget("reviews:product:{$item->product_id}");
                $created++;
            }
        }

        return $created;
    }
}
