<?php

namespace App\Observers;

use App\Http\Controllers\Api\ProductController;
use App\Models\Campaign;

/**
 * Bust the product cache whenever a campaign is created, updated, or deleted,
 * so changes to campaign–product links are reflected immediately.
 */
class CampaignObserver
{
    public function saved(Campaign $campaign): void
    {
        ProductController::bumpVersion();
    }

    public function deleted(Campaign $campaign): void
    {
        ProductController::bumpVersion();
    }
}
