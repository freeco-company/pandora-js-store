<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily Jerosse article scrape at 03:10 Asia/Taipei.
// Scraper skips already-imported URLs (dedupes by source_url) and
// applies legal-compliance sanitizer + disclaimer on import.
Schedule::command('scrape:jerosse', ['--type=all'])
    ->dailyAt('03:10')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scrape-jerosse.log'));

// Regenerate sitemap after scrape (04:00).
Schedule::command('sitemap:generate')
    ->dailyAt('04:00')
    ->timezone('Asia/Taipei')
    ->appendOutputTo(storage_path('logs/sitemap.log'));

// Daily compliance audit — scan every product + article, auto-fix,
// Discord-notify the summary (DISCORD_COMPLIANCE_WEBHOOK in .env).
// Runs after scrape + sitemap, so the daily run reflects fresh content.
Schedule::command('compliance:audit')
    ->dailyAt('04:30')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/compliance-audit.log'));

// Back-in-stock notifier — hourly scan
Schedule::command('stock:notify')
    ->hourly()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/stock-notify.log'));

// Review reminder (7d) + auto-review (14d) — daily at 10:00
Schedule::command('review:remind')
    ->dailyAt('10:00')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/review-remind.log'));

// Abandoned-cart recovery mail — runs every 3 hours
Schedule::command('cart:abandoned-mail')
    ->everyThreeHours()
    ->withoutOverlapping(20)
    ->appendOutputTo(storage_path('logs/abandoned-cart.log'));

// ── 本機開發專用：每天 03:00 從正式站 pull DB 覆蓋本地 ──
// 指令內建 PHP_OS_FAMILY === 'Darwin' 守門，Linux 正式機跑 schedule:run
// 也會被 guard 拒絕執行，不會循環同步自己。
Schedule::command('db:sync-prod', ['--force'])
    ->dailyAt('03:00')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/sync-prod-db.log'));
