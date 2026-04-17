<?php
namespace App\Filament\Resources\CampaignResource\Pages;
use App\Filament\Resources\CampaignResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新增活動'),
        ];
    }
}
