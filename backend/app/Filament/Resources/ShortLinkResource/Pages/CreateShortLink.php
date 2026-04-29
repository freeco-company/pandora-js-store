<?php

namespace App\Filament\Resources\ShortLinkResource\Pages;

use App\Filament\Resources\ShortLinkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShortLink extends CreateRecord
{
    protected static string $resource = ShortLinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
