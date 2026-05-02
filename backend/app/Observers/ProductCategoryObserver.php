<?php

namespace App\Observers;

use App\Http\Controllers\Api\ProductController;
use App\Models\ProductCategory;
use App\Services\FrontendCacheService;

/**
 * Categories drive nav + /products/category/[slug] listing. Bump backend
 * cache version (categories are part of the product index payload via slug
 * filter) and purge frontend caches.
 */
class ProductCategoryObserver
{
    public function __construct(private readonly FrontendCacheService $frontendCache) {}

    public function saved(ProductCategory $category): void
    {
        ProductController::bumpVersion();
        $this->bustFrontend($category);
    }

    public function deleted(ProductCategory $category): void
    {
        ProductController::bumpVersion();
        $this->bustFrontend($category);
    }

    private function bustFrontend(ProductCategory $category): void
    {
        $slugs = array_values(array_filter(array_unique([
            $category->slug,
            $category->getOriginal('slug'),
        ])));

        $tags = ['product-categories', 'products'];
        $paths = ['/', '/products'];
        foreach ($slugs as $slug) {
            $paths[] = "/products/category/{$slug}";
        }

        $this->frontendCache->purge(tags: $tags, paths: $paths);
    }
}
