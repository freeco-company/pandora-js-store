<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $seo = $this->record->seoMeta;
        $data['seoMeta'] = $seo ? $seo->only(['title', 'description', 'focus_keyword', 'og_image']) : [];
        return $data;
    }

    protected function afterSave(): void
    {
        $seoData = collect($this->data['seoMeta'] ?? [])->filter()->all();

        if (empty($seoData)) {
            $this->record->seoMeta?->delete();
            return;
        }

        $this->record->seoMeta()->updateOrCreate(
            ['metable_type' => $this->record->getMorphClass(), 'metable_id' => $this->record->id],
            $seoData,
        );
    }
}
