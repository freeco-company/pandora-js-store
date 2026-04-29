<?php

namespace App\Filament\Resources\FranchiseConsultationResource\Pages;

use App\Filament\Resources\FranchiseConsultationResource;
use Filament\Resources\Pages\ListRecords;

class ListFranchiseConsultations extends ListRecords
{
    protected static string $resource = FranchiseConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
