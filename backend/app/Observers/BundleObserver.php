<?php

namespace App\Observers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Bundle;
use App\Services\FrontendCacheService;

/**
 * Bundles live under /bundles/{slug} and surface on parent campaign pages.
 * Bust both, plus the products tag (campaign bundle pricing affects product
 * cards).
 */
class BundleObserver
{
    public function __construct(private readonly FrontendCacheService $frontendCache) {}

    public function saved(Bundle $bundle): void
    {
        ProductController::bumpVersion();
        $this->bustFrontend($bundle);
    }

    public function deleted(Bundle $bundle): void
    {
        ProductController::bumpVersion();
        $this->bustFrontend($bundle);
    }

    private function bustFrontend(Bundle $bundle): void
    {
        $slugs = array_values(array_filter(array_unique([
            $bundle->slug,
            $bundle->getOriginal('slug'),
        ])));

        $tags = ['bundles', 'campaigns', 'products'];
        $paths = [];
        foreach ($slugs as $slug) {
            $tags[] = "bundle:{$slug}";
            $paths[] = "/bundles/{$slug}";
        }

        // Parent campaign page also renders this bundle.
        $campaign = $bundle->campaign;
        if ($campaign && $campaign->slug) {
            $tags[] = "campaign:{$campaign->slug}";
            $paths[] = "/campaigns/{$campaign->slug}";
        }

        $this->frontendCache->purge(tags: $tags, paths: $paths);
    }
}
