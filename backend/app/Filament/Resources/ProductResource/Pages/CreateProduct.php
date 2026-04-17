<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $seoData = collect($this->data['seoMeta'] ?? [])->filter()->all();

        if (!empty($seoData)) {
            $this->record->seoMeta()->create($seoData);
        }
    }
}
