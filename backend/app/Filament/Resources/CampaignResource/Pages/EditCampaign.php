<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected array $pendingBuyItems = [];
    protected array $pendingGiftItems = [];

    /** Hydrate repeater state from the existing pivot rows. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['buy_items_ui'] = $this->record->buyItems->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity' => (int) $p->pivot->quantity,
        ])->values()->toArray();

        $data['gift_items_ui'] = $this->record->giftItems->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity' => (int) $p->pivot->quantity,
        ])->values()->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingBuyItems = $data['buy_items_ui'] ?? [];
        $this->pendingGiftItems = $data['gift_items_ui'] ?? [];
        unset($data['buy_items_ui'], $data['gift_items_ui']);
        return $data;
    }

    protected function afterSave(): void
    {
        syncBundlePivot($this->record, $this->pendingBuyItems, $this->pendingGiftItems);
    }
}
