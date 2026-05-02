<?php

namespace App\Console\Commands;

use App\Services\DiscordNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Checkout funnel weekly report — pinpoint where /cart→paid drops.
 *
 * Pre-v2.12.0 we only knew the overall cart→checkout drop (~65% real
 * after internal traffic was excluded). With the new sub-step events
 * (checkout_payment_selected / checkout_submit_attempt / checkout_submit_failed)
 * each "stuck step" leaves a fingerprint:
 *
 *   - Few submit_attempts vs many /checkout visits → form abandonment
 *     (recipient name / phone / address fields)
 *   - submit_failed ≥ 30% of submit_attempt → validation or ECPay
 *     redirect failure (look at toast errors / sentry)
 *   - Few payment_selected vs /checkout visits → users never even
 *     touch the payment radio (ecpay_credit is default — could mean
 *     they bail before scrolling that far, or just accept default)
 *
 * Output: Discord embed in the ads_strategy channel. Schedule: every
 * Sunday 22:00 Asia/Taipei (= Sun 14:00 UTC) for a rolling 7-day window.
 */
class CheckoutFunnelReportCmd extends Command
{
    protected $signature = 'analytics:checkout-funnel
                            {--days=7 : Rolling window in days}
                            {--dry : Print locally without posting to Discord}';

    protected $description = 'Weekly checkout funnel report — locates the cart→paid drop-off step';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $start = now()->subDays($days);

        $metrics = $this->collectMetrics($start);
        [$diagnosis, $color] = $this->diagnose($metrics);

        $description = $this->renderFunnel($metrics, $days);
        $fields = $this->renderFields($metrics, $diagnosis);

        if ($this->option('dry')) {
            $this->line("【乾跑】Checkout 漏斗報告（最近 {$days} 天）");
            $this->line($description);
            foreach ($fields as $f) {
                $this->line("• {$f['name']}: {$f['value']}");
            }
            return self::SUCCESS;
        }

        $title = "🛒 Checkout 漏斗 · 最近 {$days} 天";
        $sent = DiscordNotifier::adsStrategy()->embed($title, $description, $fields, $color);

        $this->info($sent ? 'Sent to Discord ads_strategy channel.' : 'Discord webhook not configured — nothing sent.');
        return self::SUCCESS;
    }

    /**
     * Pull every funnel-stage count from visits + cart_events. Each metric
     * is "distinct sessions reaching this stage", not raw event count, so
     * step-to-step ratios are interpretable as user drop-off rates.
     *
     * Internal traffic is excluded via is_internal=false on both tables;
     * see config/analytics.php.
     *
     * @return array<string, int>
     */
    private function collectMetrics(Carbon $start): array
    {
        $sessionsByPath = fn (string $path) => (int) DB::table('visits')
            ->where('visited_at', '>=', $start)
            ->where('is_internal', false)
            ->where('path', $path)
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');

        $sessionsByEvent = fn (string $eventType) => (int) DB::table('cart_events')
            ->where('created_at', '>=', $start)
            ->where('is_internal', false)
            ->where('event_type', $eventType)
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');

        $uv = (int) DB::table('visits')
            ->where('visited_at', '>=', $start)
            ->where('is_internal', false)
            ->where('referer_source', '!=', 'bot')
            ->distinct('visitor_id')
            ->count('visitor_id');

        return [
            'uv'                => $uv,
            'cart_visits'       => $sessionsByPath('/cart'),
            'add_to_cart'       => $sessionsByEvent('add_to_cart'),
            'begin_checkout'    => $sessionsByEvent('begin_checkout'),
            'checkout_visits'   => $sessionsByPath('/checkout'),
            'payment_selected'  => $sessionsByEvent('checkout_payment_selected'),
            'submit_attempt'    => $sessionsByEvent('checkout_submit_attempt'),
            'submit_failed'     => $sessionsByEvent('checkout_submit_failed'),
            'purchase'          => $sessionsByEvent('purchase'),
        ];
    }

    /**
     * Three hypotheses, ordered by severity. The first one whose threshold
     * is hit wins — if multiple are true the worst gets surfaced. Colors
     * map to Discord embed: red = bad, yellow = caution, green = OK.
     *
     * Thresholds were calibrated against pre-deploy baseline (cart→
     * checkout 65% drop) and treat <50% step retention as the alert level.
     *
     * @param array<string, int> $m
     * @return array{0: string, 1: int}  [diagnosis_label, embed_color]
     */
    private function diagnose(array $m): array
    {
        $checkout = $m['checkout_visits'];
        $attempt  = $m['submit_attempt'];
        $failed   = $m['submit_failed'];
        $purchase = $m['purchase'];

        // Sample-size guard — too small to draw conclusions.
        if ($checkout < 5) {
            return ['📊 樣本太小，建議資料累積到 ≥ 5 個 /checkout sessions 後再判斷', 0xC4A052];
        }

        // 1) Submit failure rate — high % means the form is being filled
        //    and tried but rejected. Look at validation or ECPay logs.
        if ($attempt > 0 && $failed / $attempt >= 0.30) {
            return [
                '🔥 **Submit failure rate '.round($failed / $attempt * 100).'%** — 表單填好但送出失敗，查 validation rule + ECPay redirect log',
                0xE74C3C,
            ];
        }

        // 2) Form abandonment — they reached /checkout but never even
        //    tried submitting. Friction in the form fields themselves.
        if ($checkout > 0 && $attempt / $checkout < 0.40) {
            return [
                '📝 **'.round((1 - $attempt / $checkout) * 100).'% 進到結帳頁不送出** — 填單流程有摩擦，看 recipient / phone / address 欄位是不是太多',
                0xE67E22,
            ];
        }

        // 3) Healthy funnel.
        $convRate = $checkout > 0 ? round($purchase / $checkout * 100, 1) : 0;
        return [
            "✅ 漏斗健康 · /checkout → 付款 {$convRate}%",
            0x2E7D32,
        ];
    }

    /**
     * One-line drop-off summary in the embed description, plus a
     * monospace funnel ladder. Discord embed descriptions render
     * markdown, so we use ``` for the ladder so spacing stays.
     *
     * @param array<string, int> $m
     */
    private function renderFunnel(array $m, int $days): string
    {
        $rows = [
            ['UV',                   $m['uv']],
            ['/cart 訪問',           $m['cart_visits']],
            ['加入購物車',           $m['add_to_cart']],
            ['begin_checkout',       $m['begin_checkout']],
            ['/checkout 訪問',       $m['checkout_visits']],
            ['選付款方式',           $m['payment_selected']],
            ['送出嘗試',             $m['submit_attempt']],
            ['送出失敗',             $m['submit_failed']],
            ['購買完成',             $m['purchase']],
        ];

        $lines = ['```', "近 {$days} 天 (排除 internal traffic)"];
        $prev = null;
        foreach ($rows as [$label, $count]) {
            $pct = $prev !== null && $prev > 0
                ? '  ('.round($count / $prev * 100).'% of 上一階)'
                : '';
            $lines[] = sprintf('%-18s %5d%s', $label, $count, $pct);
            $prev = $count;
        }
        $lines[] = '```';
        return implode("\n", $lines);
    }

    /**
     * Key ratios + diagnosis surfaced as Discord fields so phone
     * notifications still convey signal even if the ladder is collapsed.
     *
     * @param array<string, int> $m
     * @return array<int, array{name:string,value:string,inline?:bool}>
     */
    private function renderFields(array $m, string $diagnosis): array
    {
        $cartToCheckout = $m['cart_visits'] > 0
            ? round(($m['cart_visits'] - $m['checkout_visits']) / $m['cart_visits'] * 100, 1).'%'
            : '—';
        $checkoutToPaid = $m['checkout_visits'] > 0
            ? round($m['purchase'] / $m['checkout_visits'] * 100, 1).'%'
            : '—';
        $submitFailRate = $m['submit_attempt'] > 0
            ? round($m['submit_failed'] / $m['submit_attempt'] * 100, 1).'%'
            : '—';

        return [
            ['name' => '/cart → /checkout 流失', 'value' => $cartToCheckout, 'inline' => true],
            ['name' => '/checkout → 付款轉換',   'value' => $checkoutToPaid, 'inline' => true],
            ['name' => '送出失敗率',             'value' => $submitFailRate, 'inline' => true],
            ['name' => '🩺 診斷',                'value' => $diagnosis, 'inline' => false],
        ];
    }
}
