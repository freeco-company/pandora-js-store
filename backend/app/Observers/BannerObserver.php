<?php

namespace App\Observers;

use App\Models\Banner;
use App\Services\FrontendCacheService;
use Illuminate\Support\Facades\Storage;

class BannerObserver
{
    public function __construct(private FrontendCacheService $cache) {}

    /**
     * Auto-extract image dimensions so the frontend can size the hero
     * container to match whatever aspect the admin uploaded — no more
     * giant square banners stretched across a wide slot.
     */
    public function saving(Banner $banner): void
    {
        $this->syncDimensions($banner, 'image', 'image_width', 'image_height');
        $this->syncDimensions($banner, 'mobile_image', 'mobile_image_width', 'mobile_image_height');
    }

    public function saved(Banner $banner): void
    {
        $this->bust();
    }

    public function deleted(Banner $banner): void
    {
        $this->bust();
    }

    private function syncDimensions(Banner $banner, string $col, string $wCol, string $hCol): void
    {
        if (! $banner->isDirty($col)) return;

        if (empty($banner->$col)) {
            $banner->$wCol = null;
            $banner->$hCol = null;
            return;
        }

        $path = Storage::disk('public')->path($banner->$col);
        if (! is_file($path)) return;

        $info = @getimagesize($path);
        if ($info) {
            $banner->$wCol = $info[0];
            $banner->$hCol = $info[1];
        }
    }

    private function bust(): void
    {
        $this->cache->purge(tags: ['banners'], paths: ['/']);
    }
}
