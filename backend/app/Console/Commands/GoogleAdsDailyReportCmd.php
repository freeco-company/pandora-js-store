<?php

namespace App\Console\Commands;

use App\Services\DiscordNotifier;
use App\Services\GoogleAdsService;
use Illuminate\Console\Command;

/**
 * Posts yesterday's Google Ads performance to Discord.
 *
 * Designed for 09:00 Asia/Taipei daily — hits the API for yesterday's
 * aggregate + wasteful search terms, formats into a single Discord embed.
 *
 * Falls back gracefully when the API is not configured (returns 0) —
 * lets the rest of the schedule run without scary errors in dev/staging.
 */
class GoogleAdsDailyReportCmd extends Command
{
    protected $signature = 'ads:daily-report {--dry : Print the embed locally without posting to Discord}';

    protected $description = 'Fetch yesterday Google Ads metrics and post a summary to Discord';

    public function handle(GoogleAdsService $ads, DiscordNotifier $_unused): int
    {
        if (! $ads->isConfigured()) {
            $this->warn('Google Ads API not configured (check .env). Skipping.');
            return self::SUCCESS;
        }

        $m     = $ads->getYesterdayMetrics();
        $waste = $ads->getWastefulSearchTerms(days: 7, minSpend: 50, limit: 5);
        $top   = $ads->getTopSearchTerms(days: 7, limit: 5);

        [$title, $description, $fields, $color] = $this->buildEmbed($m, $waste, $top);

        if ($this->option('dry')) {
            $this->line("=== {$title} ===");
            $this->line($description);
            foreach ($fields as $f) {
                $this->line("--- {$f['name']} ---");
                $this->line($f['value']);
            }
            return self::SUCCESS;
        }

        $notifier = DiscordNotifier::ads();
        if (! $notifier->isEnabled()) {
            $this->warn('DISCORD_ADS_WEBHOOK (and fallbacks) not set. Skipping post.');
            return self::SUCCESS;
        }

        $ok = $notifier->embed($title, $description, $fields, $color);
        if ($ok) {
            $this->info('Posted Google Ads daily report to Discord.');
            return self::SUCCESS;
        }

        $this->error('Failed to post to Discord (check logs).');
        return self::FAILURE;
    }

    /**
     * @param array<string, mixed> $m
     * @param list<array<string, mixed>> $waste
     * @param list<array<string, mixed>> $top
     *
     * @return array{0:string, 1:string, 2:list<array{name:string, value:string, inline?:bool}>, 3:int}
     */
    private function buildEmbed(array $m, array $waste, array $top): array
    {
        $date = $m['date'];
        $spend       = number_format($m['spend']);
        $clicks      = number_format($m['clicks']);
        $impressions = number_format($m['impressions']);
        $conversions = $m['conversions'];
        $cpc = $m['cpc'] > 0 ? 'NT$' . number_format($m['cpc']) : '—';
        $cpa = $m['conversions'] > 0 ? 'NT$' . number_format($m['cpa']) : '—';
        $roas = $m['roas'] > 0 ? "{$m['roas']}x" : '—';
        $ctr = "{$m['ctr']}%";

        $title = "📊 Google Ads 昨日報告 ({$date})";

        $description = sprintf(
            "💰 **花費**：NT\$%s\n👁 **曝光**：%s　🖱 **點擊**：%s（CTR %s）\n✅ **轉換**：%s（CPA %s / ROAS %s）\n💵 CPC %s",
            $spend, $impressions, $clicks, $ctr, $conversions, $cpa, $roas, $cpc
        );

        $fields = [];

        if (! empty($waste)) {
            $lines = [];
            foreach ($waste as $i => $w) {
                $n = $i + 1;
                $lines[] = sprintf(
                    "%d. `%s` — NT\$%s / %s 次點擊 / 0 轉換",
                    $n, mb_strimwidth($w['term'], 0, 40, '…'),
                    number_format($w['spend']), number_format($w['clicks'])
                );
            }
            $fields[] = [
                'name'   => '🔥 燒錢但沒轉換的字（近 7 天）',
                'value'  => implode("\n", $lines),
                'inline' => false,
            ];
        }

        if (! empty($top)) {
            $lines = [];
            foreach ($top as $i => $t) {
                $tag = $t['conversions'] > 0 ? '✓' : '·';
                $lines[] = sprintf(
                    "%s `%s` — NT\$%s / %s 次 / %s 轉換",
                    $tag, mb_strimwidth($t['term'], 0, 40, '…'),
                    number_format($t['spend']), number_format($t['clicks']), $t['conversions']
                );
            }
            $fields[] = [
                'name'   => '🎯 花最多錢的字（近 7 天）',
                'value'  => implode("\n", $lines),
                'inline' => false,
            ];
        }

        if (! empty($waste)) {
            $fields[] = [
                'name'   => '📌 建議',
                'value'  => '考慮把上述 🔥 清單加進 Google Ads → 否定關鍵字，減少浪費。',
                'inline' => false,
            ];
        }

        // Brand color: gold-brown (success) when ROAS > 2, warning (amber) < 1, else brand
        $color = 10447166; // #9F6B3E brand
        if ($m['roas'] >= 2) $color = 3066993;      // green
        elseif ($m['roas'] > 0 && $m['roas'] < 1) $color = 15105570; // amber

        return [$title, $description, $fields, $color];
    }
}
