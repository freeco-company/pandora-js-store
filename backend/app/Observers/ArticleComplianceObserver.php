<?php

namespace App\Observers;

use App\Http\Controllers\Api\ArticleController;
use App\Models\Article;
use App\Services\IndexNowService;
use App\Services\LegalContentSanitizer;

/**
 * Transparently sanitize article text + bump API cache version on save.
 * Idempotent.
 */
class ArticleComplianceObserver
{
    public function __construct(private readonly LegalContentSanitizer $sanitizer) {}

    public function saving(Article $article): void
    {
        if ($article->isDirty('title') && $article->title) {
            $article->title = $this->sanitizer->sanitizeText($article->title);
        }
        if ($article->isDirty('excerpt') && $article->excerpt) {
            $article->excerpt = $this->sanitizer->sanitizeText($article->excerpt);
        }
        if ($article->isDirty('content') && $article->content) {
            // Strip redundant promo-time paragraphs (captured in promo_ends_at),
            // then sanitize + disclaimer.
            $content = $this->sanitizer->stripPromoTimeBlocks($article->content);
            $article->content = $this->sanitizer->process($content, 'article');
        }
    }

    public function saved(Article $article): void
    {
        ArticleController::bumpVersion();
        $this->pingIndexNow($article);
    }

    /**
     * Ping IndexNow only when published and the public-facing URL/content
     * changed. Skips draft saves so we don't waste submissions during edit.
     */
    private function pingIndexNow(Article $article): void
    {
        if ($article->status !== 'published') return;

        $triggers = ['slug', 'title', 'content', 'excerpt', 'status', 'featured_image'];
        $changed = count(array_intersect($triggers, array_keys($article->getChanges()))) > 0
            || $article->wasRecentlyCreated;
        if (! $changed) return;

        $host = (string) config('services.indexnow.host');
        $url = "https://{$host}/articles/{$article->slug}";
        IndexNowService::fromConfig()->submitOne($url);
    }

    public function deleted(Article $article): void
    {
        ArticleController::bumpVersion();
    }
}
