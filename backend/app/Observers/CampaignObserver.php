<?php

namespace App\Observers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Campaign;
use App\Services\FrontendCacheService;

/**
 * Bust the product cache + frontend (Next.js ISR + Cloudflare) whenever a
 * campaign is created, updated, or deleted, so changes to campaign-product
 * links and bundle definitions surface within seconds.
 */
class CampaignObserver
{
    public function __construct(private readonly FrontendCacheService $frontendCache) {}

    public function saved(Campaign $campaign): void
    {
        ProductController::bumpVersion();
        $this->bustFrontend($campaign);
    }

    public function deleted(Campaign $campaign): void
    {
        ProductController::bumpVersion();
        $this->bustFrontend($campaign);
    }

    private function bustFrontend(Campaign $campaign): void
    {
        $slugs = array_values(array_filter(array_unique([
            $campaign->slug,
            $campaign->getOriginal('slug'),
        ])));

        // Campaigns surface bundle pricing on product cards too, so bust the
        // products tag/path along with campaign-specific keys.
        $tags = ['campaigns', 'bundles', 'products'];
        $paths = ['/', '/products'];
        foreach ($slugs as $slug) {
            $tags[] = "campaign:{$slug}";
            $paths[] = "/campaigns/{$slug}";
        }

        $this->frontendCache->purge(tags: $tags, paths: $paths);
    }
}
