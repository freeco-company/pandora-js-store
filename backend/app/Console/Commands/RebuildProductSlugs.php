<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSlugger;
use Illuminate\Console\Command;

/**
 * One-time/idempotent: rebuild each product's slug using the cleaner
 * Chinese naming rules, preserving the original in slug_legacy for 301s.
 *
 * Run:  php artisan products:rebuild-slugs --dry-run   (preview)
 *       php artisan products:rebuild-slugs              (apply)
 */
class RebuildProductSlugs extends Command
{
    protected $signature = 'products:rebuild-slugs {--dry-run : Show before/after without saving}';

    protected $description = 'Regenerate product slugs to Taiwanese-Chinese clean form. Preserves old in slug_legacy for 301.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $changed = 0;

        foreach (Product::orderBy('id')->get() as $p) {
            $newSlug = ProductSlugger::fromName($p->name);
            if ($newSlug === $p->slug) continue;

            $uniqueNew = ProductSlugger::unique($newSlug, Product::class, $p->id);

            $rows[] = [$p->id, mb_strimwidth($p->slug, 0, 40, '…'), mb_strimwidth($uniqueNew, 0, 40, '…')];

            if (! $dryRun) {
                // Preserve original slug for 301 redirect if not already saved
                if (empty($p->slug_legacy)) $p->slug_legacy = $p->slug;
                $p->slug = $uniqueNew;
                $p->saveQuietly();   // bypass observer re-sanitize
                $changed++;
            }
        }

        $this->table(['ID', 'Old slug', 'New slug'], $rows);
        $this->info($dryRun ? "Dry-run: would rename {$this->rowCount($rows)} products." : "Renamed {$changed} products.");

        return self::SUCCESS;
    }

    private function rowCount(array $rows): int
    {
        return count($rows);
    }
}
