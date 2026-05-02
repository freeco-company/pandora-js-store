<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ADR-007 §6 #4 mitigation (b) — hourly identity reconcile from Pandora Core.
// Catches webhooks the publisher dropped / dead-lettered.
Schedule::command('identity:reconcile')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

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

// Daily article image audit — 外站圖落地 / 死圖移除（避免破圖 icon）。
// 跑在 scrape 之後、compliance 之前，這樣下游 widget / RSS 拿到的內容已乾淨。
Schedule::command('articles:audit-images')
    ->dailyAt('04:15')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/articles-audit-images.log'));

// Daily compliance audit — scan every product + article, auto-fix,
// Discord-notify the summary (DISCORD_COMPLIANCE_WEBHOOK in .env).
// Runs after scrape + sitemap, so the daily run reflects fresh content.
Schedule::command('compliance:audit')
    ->dailyAt('04:30')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/compliance-audit.log'));

// Article ↔ product internal-linking — incremental (last 24h) at 04:45.
// First-time/full-rebuild: run `php artisan content:link-articles-products --all`.
Schedule::command('content:link-articles-products')
    ->dailyAt('04:45')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(20)
    ->appendOutputTo(storage_path('logs/article-product-link.log'));

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

// CVS pickup reminder — daily 09:00. Nudges customers whose CVS parcels
// have been sitting at the store for 5 days (CVS auto-returns at day 7).
// One nudge per parcel via pickup_reminder_sent_at.
Schedule::command('cvs:pickup-reminder')
    ->dailyAt('09:00')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(20)
    ->appendOutputTo(storage_path('logs/cvs-pickup-reminder.log'));

// Bundle wishlist alert — every 6h. When a campaign bundle is about to
// expire (< 24h) and contains a wishlisted product, ping the customer.
// Dedupe via bundle_wishlist_alerts so they're never nudged twice for the
// same bundle.
Schedule::command('bundle:wishlist-alert')
    ->everySixHours()
    ->withoutOverlapping(15)
    ->appendOutputTo(storage_path('logs/bundle-wishlist-alert.log'));

// SEO weekly snapshot — Mondays 06:00. Captures Core Web Vitals for the
// 3 representative URL templates so the Filament dashboard can show trends.
// No-ops if PAGESPEED_API_KEY is unset.
Schedule::command('seo:weekly-snapshot')
    ->weeklyOn(1, '06:00')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/seo-snapshot.log'));

// Pipeline daily report — 09:10 Asia/Taipei. Merges Ads + SEO + GEO + funnel
// into a single Discord embed so the team isn't cross-referencing 4 different
// posts every morning. Supersedes `ads:daily-report` (which is kept as an
// unscheduled admin command for manual Ads-only inspection). Silently skips
// if DISCORD_ADS_WEBHOOK missing.
Schedule::command('pipeline:daily-report')
    ->dailyAt('09:10')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/pipeline-daily-report.log'));

// Weekly checkout funnel — pinpoints where /cart→paid drops using the
// sub-step events added in v2.12.0 (payment_selected / submit_attempt /
// submit_failed). Sunday 22:00 Asia/Taipei aligns with end-of-week
// recap; rolling 7-day window keeps signal fresh without drowning in
// noise. Posts to ads_strategy Discord channel.
Schedule::command('analytics:checkout-funnel')
    ->weeklyOn(0, '22:00') // 0 = Sunday
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/checkout-funnel.log'));

// COD pending_confirmation 訂單：24h 寄提醒 / 48h 自動取消（還原庫存 + coupon + 通知）
Schedule::command('cod:auto-cancel-unconfirmed')
    ->everyThirtyMinutes()
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cod-auto-cancel.log'));

// 銀行轉帳訂單：24h 寄提醒 / 48h 未付款自動取消（避免無效 hold 庫存 / coupon）
Schedule::command('bank-transfer:auto-cancel')
    ->everyThirtyMinutes()
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/bank-transfer-auto-cancel.log'));

// Pandora Core identity shadow-mirror outbox dispatch — every 5 min.
// Pulls outbox_identity_events.status=pending + due → ProcessIdentityOutbox jobs.
// Feature-flag IDENTITY_MIRROR_ENABLED 控制 service 層是否寫 outbox；schedule
// 永遠跑（idempotent，沒 pending 就空轉）。ADR-001 §4.1 Step 1.
Schedule::command('mirror:dispatch-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/identity-mirror.log'));

// Franchise webhook outbox sweeper — every minute.
// 母艦 → 朵朵 (pandora-meal) franchisee.activated/deactivated events。
// Customer.is_franchisee 變化時 observer 寫一筆到 franchise_outbox_events 並
// 立即 dispatch job；schedule 每分鐘兜底未送達 / retry。Worker 失敗 5 次後留 table。
Schedule::command('franchise:dispatch-pending')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/franchise-webhook.log'));

// ── 本機開發專用：每天 03:00 從正式站 pull DB 覆蓋本地 ──
// 指令內建 PHP_OS_FAMILY === 'Darwin' 守門，Linux 正式機跑 schedule:run
// 也會被 guard 拒絕執行，不會循環同步自己。
Schedule::command('db:sync-prod', ['--force'])
    ->dailyAt('03:00')
    ->timezone('Asia/Taipei')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/sync-prod-db.log'));
