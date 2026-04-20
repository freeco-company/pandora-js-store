<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

/**
 * AI traffic stacked by bot_type. Honors the dashboard date filter;
 * falls back to a 14-day window if no filter is set.
 * "bot" rows = crawler hits (ClaudeBot etc.); "user" rows = humans
 * arriving from AI sites (chatgpt.com referer etc.). Combined here —
 * toggle the filter pill to split.
 */
class AiTrafficChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'AI 爬蟲 × AI 來源訪客';

    protected static ?int $sort = 10;

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'all';

    private const BOT_LABELS = [
        'claude' => 'Claude (Anthropic)',
        'gpt' => 'ChatGPT (OpenAI)',
        'perplexity' => 'Perplexity',
        'google_ai' => 'Google AI',
        'apple' => 'Apple',
        'bytedance' => 'ByteDance',
        'meta' => 'Meta',
        'amazon' => 'Amazon',
        'common_crawl' => 'CommonCrawl',
        'cohere' => 'Cohere',
        'other' => '其他',
    ];

    private const BOT_COLORS = [
        'claude' => '#D97706',
        'gpt' => '#10B981',
        'perplexity' => '#8B5CF6',
        'google_ai' => '#EF4444',
        'apple' => '#6B7280',
        'bytedance' => '#000000',
        'meta' => '#1877F2',
        'amazon' => '#FF9900',
        'common_crawl' => '#A16207',
        'cohere' => '#EC4899',
        'other' => '#9CA3AF',
    ];

    protected function getFilters(): ?array
    {
        return [
            'all' => '全部',
            'bot' => 'AI 爬蟲',
            'user' => 'AI 來源訪客',
        ];
    }

    protected function getData(): array
    {
        [$start, $end] = $this->resolveRange();

        $q = DB::table('ai_visits_daily')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($this->filter !== 'all') {
            $q->where('source', $this->filter);
        }

        $rows = $q->select('date', 'bot_type', DB::raw('SUM(hits) as hits'))
            ->groupBy('date', 'bot_type')
            ->get();

        // Build [bot_type][date] => hits matrix
        $matrix = [];
        foreach ($rows as $r) {
            $matrix[$r->bot_type][$r->date] = (int) $r->hits;
        }

        $labels = [];
        $dates = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $labels[] = $cursor->format('m/d');
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $datasets = [];
        foreach (self::BOT_LABELS as $slug => $label) {
            if (!isset($matrix[$slug])) {
                continue; // skip bot_types with no hits in window
            }
            $data = array_map(fn ($d) => $matrix[$slug][$d] ?? 0, $dates);
            if (array_sum($data) === 0) {
                continue;
            }
            $datasets[] = [
                'label' => $label,
                'data' => $data,
                'backgroundColor' => self::BOT_COLORS[$slug] ?? '#9CA3AF',
                'borderColor' => self::BOT_COLORS[$slug] ?? '#9CA3AF',
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function resolveRange(): array
    {
        $start = $this->pageFilters['startDate'] ?? null;
        $end = $this->pageFilters['endDate'] ?? null;

        $start = $start ? Carbon::parse($start)->startOfDay() : Carbon::today()->subDays(13);
        $end = $end ? Carbon::parse($end)->startOfDay() : Carbon::today();

        // Cap absurdly long ranges so the x-axis stays readable
        if ($start->diffInDays($end) > 90) {
            $start = (clone $end)->subDays(90);
        }

        return [$start, $end];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
        ];
    }
}
