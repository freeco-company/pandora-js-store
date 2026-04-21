<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * AI traffic stacked by bot_type. Has its own range pill (7/14/30 days)
 * because the whole point of this chart is watching the AI trend over
 * weeks — tying it to the dashboard's "today" filter would collapse it
 * to one bar, which tells you nothing about whether AI crawlers are
 * visiting more this week than last.
 *
 * "bot" rows = crawler hits (ClaudeBot etc.); "user" rows = humans
 * arriving from AI sites (chatgpt.com referer etc.). Combined by
 * default — change the source pill to split.
 */
class AiTrafficChart extends ChartWidget
{
    protected ?string $heading = 'AI 爬蟲 × AI 來源訪客';

    protected static ?int $sort = 10;

    protected ?string $maxHeight = '300px';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'all_14';

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
        // Combined source × range pill so we don't need two separate
        // widgets. Format: "<source>_<days>" — parsed in getData().
        return [
            'all_7' => '全部 · 近 7 天',
            'all_14' => '全部 · 近 14 天',
            'all_30' => '全部 · 近 30 天',
            'bot_14' => 'AI 爬蟲 · 近 14 天',
            'bot_30' => 'AI 爬蟲 · 近 30 天',
            'user_14' => 'AI 來源訪客 · 近 14 天',
            'user_30' => 'AI 來源訪客 · 近 30 天',
        ];
    }

    protected function getData(): array
    {
        [$source, $days] = $this->parseFilter();
        $end = Carbon::today();
        $start = (clone $end)->subDays($days - 1);

        $q = DB::table('ai_visits_daily')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($source !== 'all') {
            $q->where('source', $source);
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

    /** @return array{0: string, 1: int} [source, days] */
    private function parseFilter(): array
    {
        $parts = explode('_', $this->filter ?? 'all_14');
        $source = $parts[0] ?? 'all';
        $days = (int) ($parts[1] ?? 14);
        if (! in_array($source, ['all', 'bot', 'user'], true)) $source = 'all';
        if (! in_array($days, [7, 14, 30], true)) $days = 14;
        return [$source, $days];
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
