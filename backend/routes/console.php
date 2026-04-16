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
