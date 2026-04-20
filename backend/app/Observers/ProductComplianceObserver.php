<?php

namespace App\Observers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Product;
use App\Services\IndexNowService;
use App\Services\LegalContentSanitizer;

/**
 * Transparently sanitize product text on any save path
 * (Filament admin edits, API imports, seeders, manual wp:import).
 * Also bumps the product cache version so admin edits appear within seconds.
 * Idempotent — re-saving a clean product is a no-op.
 */
class ProductComplianceObserver
{
    public function __construct(private readonly LegalContentSanitizer $sanitizer) {}

    public function saving(Product $product): void
    {
        if ($product->isDirty('name') && $product->name) {
            $product->name = $this->sanitizer->sanitizeText($product->name);
        }
        if ($product->isDirty('short_description') && $product->short_description) {
            $product->short_description = $this->sanitizer->sanitize($product->short_description);
        }
        if ($product->isDirty('description') && $product->description) {
            $product->description = $this->sanitizer->process($product->description, 'product');
        }
    }

    public function saved(Product $product): void
    {
        ProductController::bumpVersion();
        $this->pingIndexNow($product);
    }

    /**
     * Ping IndexNow only when a public-facing field changed. Skips
     * inventory-only saves so we don't submit on every stock tweak.
     */
    private function pingIndexNow(Product $product): void
    {
        if (! $product->is_active) return;

        $triggers = ['slug', 'name', 'description', 'short_description', 'price', 'combo_price', 'vip_price', 'is_active', 'featured_image'];
        $changed = count(array_intersect($triggers, array_keys($product->getChanges()))) > 0
            || $product->wasRecentlyCreated;
        if (! $changed) return;

        $host = (string) config('services.indexnow.host');
        $url = "https://{$host}/products/{$product->slug}";
        IndexNowService::fromConfig()->submitOne($url);
    }

    public function deleted(Product $product): void
    {
        ProductController::bumpVersion();
    }
}
