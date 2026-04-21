<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use App\Models\Visit;
use Filament\Resources\Pages\ListRecords;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    public function getTitle(): string
    {
        return '當日流量';
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        // Show quick stats above the table — UV, PV, and source breakdown
        // for today. Cheap because the column has a compound index.
        $today = today();
        $uv = Visit::whereDate('visited_at', $today)->distinct('visitor_id')->count('visitor_id');
        $pv = Visit::whereDate('visited_at', $today)->count();
        $bySource = Visit::whereDate('visited_at', $today)
            ->selectRaw('referer_source, COUNT(*) as c')
            ->groupBy('referer_source')
            ->pluck('c', 'referer_source')
            ->toArray();

        $sourceLabels = [
            'direct' => '直接',
            'google' => 'Google',
            'google_ads' => 'GAds',
            'facebook' => 'FB',
            'instagram' => 'IG',
            'line' => 'LINE',
            'bing' => 'Bing',
            'email' => 'Email',
            'other' => '其他',
        ];
        $parts = [];
        foreach ($sourceLabels as $k => $label) {
            if (! empty($bySource[$k])) $parts[] = "{$label} {$bySource[$k]}";
        }

        return sprintf('今日 UV %d · PV %d · %s', $uv, $pv, $parts ? implode('　', $parts) : '—');
    }
}
