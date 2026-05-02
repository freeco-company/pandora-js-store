<?php

namespace App\Observers;

use App\Models\Review;
use App\Services\FrontendCacheService;
use Illuminate\Support\Facades\Cache;

/**
 * Bust review caches when admin toggles visibility / replies / a customer
 * posts a new review. ReviewController stores by product id; frontend tags
 * by slug — invalidate both layers.
 */
class ReviewObserver
{
    public function __construct(private readonly FrontendCacheService $frontendCache) {}

    public function saved(Review $review): void
    {
        $this->bust($review);
    }

    public function deleted(Review $review): void
    {
        $this->bust($review);
    }

    private function bust(Review $review): void
    {
        // Backend keys (see ReviewController::index/aggregate)
        Cache::forget("reviews:product:{$review->product_id}");
        Cache::forget('reviews:aggregate');

        $tags = ['reviews'];
        $paths = ['/reviews'];

        $product = $review->product;
        if ($product && $product->slug) {
            $tags[] = "reviews:{$product->slug}";
            // The product detail page renders aggregate stars; bust it too.
            $paths[] = "/products/{$product->slug}";
        }

        $this->frontendCache->purge(tags: $tags, paths: $paths);
    }
}
