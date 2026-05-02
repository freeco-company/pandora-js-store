<?php

namespace App\Observers;

use App\Models\Popup;
use App\Services\FrontendCacheService;

/**
 * Popups are loaded on every page load through getPopups(). When admin edits
 * one, bust the popups tag + home/products paths so the new popup shows up
 * within seconds instead of waiting out the 15-min ISR.
 */
class PopupObserver
{
    public function __construct(private readonly FrontendCacheService $frontendCache) {}

    public function saved(Popup $popup): void
    {
        $this->bust();
    }

    public function deleted(Popup $popup): void
    {
        $this->bust();
    }

    private function bust(): void
    {
        $this->frontendCache->purge(tags: ['popups'], paths: ['/', '/products']);
    }
}
