<?php

namespace App\Console\Commands;

use App\Models\Bundle;
use App\Models\Customer;
use App\Models\Wishlist;
use App\Services\LineMessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Wishlist-driven scarcity nudge.
 *
 * When a campaign bundle is about to end (< 24h to end_at) AND that
 * bundle includes a product the customer has wishlisted, push them a
 * reminder. They get a personalised "your saved item is in a deal that
 * ends today" — much higher signal than a generic campaign blast.
 *
 * Idempotent via bundle_wishlist_alerts (unique bundle_id+customer_id).
 *
 * Schedule: every 6 hours. The cron can run inside the 24h window
 * multiple times without re-sending — the dedupe table guarantees one
 * touch per bundle×customer pair.
 */
class BundleWishlistAlertCmd extends Command
{
    protected $signature = 'bundle:wishlist-alert';
    protected $description = 'Notify wishlist owners when a saved item is in a bundle ending within 24h';

    public function handle(LineMessagingService $line): int
    {
        $emailSent = 0;
        $lineSent = 0;
        $errors = 0;
        $candidates = 0;

        // Bundles whose campaign ends within the next 24h (and is currently running)
        $bundles = Bundle::with(['campaign', 'buyItems:id'])
            ->whereHas('campaign', function ($q) {
                $q->where('is_active', true)
                  ->where('start_at', '<=', now())
                  ->where('end_at', '>', now())
                  ->where('end_at', '<=', now()->addHours(24));
            })
            ->get();

        if ($bundles->isEmpty()) {
            $this->info('no bundles ending within 24h');
            return self::SUCCESS;
        }

        foreach ($bundles as $bundle) {
            $productIds = $bundle->buyItems->pluck('id');
            if ($productIds->isEmpty()) continue;

            // Customers who have wishlisted any of this bundle's products
            // AND haven't been alerted about this bundle yet.
            $customers = Customer::whereIn('id', function ($q) use ($productIds) {
                    $q->select('customer_id')->from('wishlists')->whereIn('product_id', $productIds);
                })
                ->whereNotIn('id', function ($q) use ($bundle) {
                    $q->select('customer_id')->from('bundle_wishlist_alerts')
                      ->where('bundle_id', $bundle->id);
                })
                ->get();

            $candidates += $customers->count();
            if ($customers->isEmpty()) continue;

            $bundleUrl = rtrim(config('services.ecpay.frontend_url', 'https://pandora.js-store.com.tw'), '/')
                . '/bundles/' . urlencode($bundle->slug);
            $hoursLeft = max(1, (int) round(now()->diffInMinutes($bundle->campaign->end_at) / 60));
            $price = (int) $bundle->bundlePrice();
            $value = (int) $bundle->valuePrice();
            $savings = max(0, $value - $price);

            foreach ($customers as $customer) {
                $touched = false;

                // Email
                $email = $customer->email;
                $isPlaceholder = $email && str_ends_with($email, '@line.user');
                if ($email && ! $isPlaceholder) {
                    try {
                        Mail::raw(
                            $this->emailBody($bundle, $hoursLeft, $price, $savings, $bundleUrl),
                            function ($m) use ($email, $bundle) {
                                $m->to($email)
                                  ->subject("【婕樂纖仙女館】你收藏的商品有限時組合：{$bundle->name}");
                            },
                        );
                        $emailSent++;
                        $touched = true;
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::warning('[bundle:wishlist-alert] email failed', [
                            'bundle' => $bundle->slug, 'customer' => $customer->id, 'msg' => $e->getMessage(),
                        ]);
                    }
                }

                // LINE
                if ($customer->line_id && $line->isConfigured()) {
                    $text = "婕樂仙女～你收藏的商品現在有限時優惠！\n"
                          . "「{$bundle->name}」剩 {$hoursLeft} 小時，套組價 NT$" . number_format($price)
                          . ($savings > 0 ? "、現省 NT$" . number_format($savings) : '')
                          . "～";

                    if ($line->pushText($customer->line_id, $text, [
                        ['label' => '查看活動', 'uri' => $bundleUrl],
                    ])) {
                        $lineSent++;
                        $touched = true;
                    }
                }

                if ($touched) {
                    DB::table('bundle_wishlist_alerts')->insert([
                        'bundle_id' => $bundle->id,
                        'customer_id' => $customer->id,
                        'sent_at' => now(),
                    ]);
                }
            }
        }

        $this->info("bundles {$bundles->count()} · candidates {$candidates} · email {$emailSent} · line {$lineSent} · errors {$errors}");
        return self::SUCCESS;
    }

    private function emailBody(Bundle $bundle, int $hoursLeft, int $price, int $savings, string $url): string
    {
        $savingLine = $savings > 0 ? "現省 NT$" . number_format($savings) . "！\n" : '';
        return <<<TXT
婕樂仙女您好，

你收藏的商品現在出現在限時組合裡 ——「{$bundle->name}」剩下不到 {$hoursLeft} 小時！

套組價：NT${$price}
{$savingLine}
活動限時優惠加入購物車後，整車自動以 VIP 價計算，搭配你想要的其他商品最划算。

→ 立即查看：{$url}

— 婕樂纖仙女館 FP
TXT;
    }
}
