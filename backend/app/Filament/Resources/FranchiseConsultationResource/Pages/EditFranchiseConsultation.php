<?php

namespace App\Filament\Resources\FranchiseConsultationResource\Pages;

use App\Filament\Resources\FranchiseConsultationResource;
use Filament\Resources\Pages\EditRecord;

class EditFranchiseConsultation extends EditRecord
{
    protected static string $resource = FranchiseConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
