<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    /**
     * buy_items_ui / gift_items_ui are form-only repeater state. Strip
     * from $data before Eloquent fill, then sync the campaign_product
     * pivot with role+quantity in afterCreate().
     */
    protected array $pendingBuyItems = [];
    protected array $pendingGiftItems = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingBuyItems = $data['buy_items_ui'] ?? [];
        $this->pendingGiftItems = $data['gift_items_ui'] ?? [];
        unset($data['buy_items_ui'], $data['gift_items_ui']);
        return $data;
    }

    protected function afterCreate(): void
    {
        syncBundlePivot($this->record, $this->pendingBuyItems, $this->pendingGiftItems);
    }
}

/**
 * Shared helper — sync the campaign_product pivot with role+quantity.
 * Defined at file scope so both Create and Edit pages can call it without
 * duplicating logic or creating a Concerns namespace just for one function.
 */
function syncBundlePivot(\App\Models\Campaign $campaign, array $buyItems, array $giftItems): void
{
    $pivot = [];
    foreach ($buyItems as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        if (!$pid) continue;
        $pivot[$pid] = ['role' => 'buy', 'quantity' => max(1, (int) ($row['quantity'] ?? 1))];
    }
    // Gift items keyed separately — if the same product_id appears as buy
    // AND gift, we need BOTH pivot rows. Sync-by-id can't do that, so we
    // replace rows manually.
    $campaign->products()->detach();
    foreach ($pivot as $pid => $attrs) {
        $campaign->products()->attach($pid, $attrs);
    }
    foreach ($giftItems as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        if (!$pid) continue;
        $campaign->products()->attach($pid, [
            'role' => 'gift',
            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
        ]);
    }
}
