<?php

namespace App\Observers;

use App\Models\Banner;
use App\Services\FrontendCacheService;

class BannerObserver
{
    public function __construct(private FrontendCacheService $cache) {}

    public function saved(Banner $banner): void
    {
        $this->bust();
    }

    public function deleted(Banner $banner): void
    {
        $this->bust();
    }

    private function bust(): void
    {
        $this->cache->purge(tags: ['banners'], paths: ['/']);
    }
}
