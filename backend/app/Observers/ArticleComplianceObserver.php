<?php

namespace App\Observers;

use App\Http\Controllers\Api\ArticleController;
use App\Models\Article;
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
    }

    public function deleted(Article $article): void
    {
        ArticleController::bumpVersion();
    }
}
