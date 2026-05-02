<?php

namespace App\Observers;

use App\Http\Controllers\Api\ArticleController;
use App\Models\ArticleCategory;
use App\Services\FrontendCacheService;

/**
 * Article categories filter /articles listings. Bump the article cache and
 * purge the articles tag/path so admin renames show up immediately.
 */
class ArticleCategoryObserver
{
    public function __construct(private readonly FrontendCacheService $frontendCache) {}

    public function saved(ArticleCategory $category): void
    {
        ArticleController::bumpVersion();
        $this->bust();
    }

    public function deleted(ArticleCategory $category): void
    {
        ArticleController::bumpVersion();
        $this->bust();
    }

    private function bust(): void
    {
        $this->frontendCache->purge(tags: ['articles'], paths: ['/articles']);
    }
}
