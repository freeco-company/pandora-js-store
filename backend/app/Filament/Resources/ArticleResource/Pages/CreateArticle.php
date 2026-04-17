<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected function afterCreate(): void
    {
        $seoData = collect($this->data['seoMeta'] ?? [])->filter()->all();

        if (!empty($seoData)) {
            $this->record->seoMeta()->create($seoData);
        }
    }
}
