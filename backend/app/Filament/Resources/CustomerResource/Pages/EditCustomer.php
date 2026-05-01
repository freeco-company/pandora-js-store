<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * 加盟 toggle 啟用但未填認證時間 → 自動填 now()。
     * Toggle 關閉時 verified_at 保留歷史紀錄（不清空，方便日後追溯）。
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['is_franchisee']) && empty($data['franchisee_verified_at'])) {
            $data['franchisee_verified_at'] = now();
        }

        return $data;
    }
}
