<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use App\Services\IndexNowService;
use Illuminate\Console\Command;

/**
 * One-shot bulk IndexNow submit for all published articles + active products.
 * Run after a slug repair pass, a big import, or a platform migration — the
 * per-save observer handles day-to-day updates.
 *
 * Usage:
 *   php artisan indexnow:bulk-submit            # articles + products
 *   php artisan indexnow:bulk-submit --articles
 *   php artisan indexnow:bulk-submit --products
 */
class IndexNowBulkSubmit extends Command
{
    protected $signature = 'indexnow:bulk-submit {--articles : Only articles} {--products : Only products}';

    protected $description = 'Submit all public URLs to IndexNow (Bing/Yandex) in one batch.';

    public function handle(): int
    {
        $svc = IndexNowService::fromConfig();
        if (! $svc->isEnabled()) {
            $this->warn('IndexNow disabled — set INDEXNOW_ENABLED=true + INDEXNOW_KEY.');
            return self::FAILURE;
        }

        $host = (string) config('services.indexnow.host');
        $only = $this->option('articles') ? 'articles' : ($this->option('products') ? 'products' : 'both');

        $urls = [];

        if ($only !== 'products') {
            $slugs = Article::where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('promo_ends_at')->orWhere('promo_ends_at', '>', now());
                })
                ->pluck('slug');
            foreach ($slugs as $s) {
                $urls[] = "https://{$host}/articles/{$s}";
            }
            $this->info("Articles: {$slugs->count()}");
        }

        if ($only !== 'articles') {
            $slugs = Product::where('is_active', true)->pluck('slug');
            foreach ($slugs as $s) {
                $urls[] = "https://{$host}/products/{$s}";
            }
            $this->info("Products: {$slugs->count()}");
        }

        if (empty($urls)) {
            $this->warn('No URLs to submit.');
            return self::SUCCESS;
        }

        $ok = $svc->submit($urls);
        $this->info($ok
            ? "Submitted " . count($urls) . " URLs to IndexNow."
            : "Submit failed — check logs.");

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
