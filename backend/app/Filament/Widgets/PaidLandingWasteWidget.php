<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "付費流量浪費清單 — 過去 14 天 0% 加購的廣告 landing"
 *
 * Surfaces paid-traffic landing pages (referer_source ∈ ad sources) where
 * sessions added zero items to the cart. These are the ads that should be
 * paused, re-keyworded, or pointed at a different landing — every click
 * is spend with no revenue.
 *
 * Cross-references visits.session_id with cart_events to compute the
 * add-to-cart rate per landing path. Sorted by sessions desc so the worst
 * money holes float to the top.
 */
class PaidLandingWasteWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = '🔥 付費流量浪費清單（近 14 天 · 0% 加購的廣告 landing）';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => Cache::remember(
                'paid_landing_waste:14d',
                now()->addMinutes(10),
                fn () => $this->loadRows()
            ))
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('#')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('source')
                    ->label('廣告來源')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'google_ads' => 'warning',
                        'facebook_ads' => 'info',
                        'bing_ads', 'tiktok_ads' => 'gray',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Landing 頁面')
                    ->wrap()
                    ->weight('bold')
                    ->description(fn ($record) => $record['landing_path'] ?? null),

                Tables\Columns\TextColumn::make('sessions')
                    ->label('付費 sessions')
                    ->alignEnd()
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn ($state) => number_format((int) $state)),

                Tables\Columns\TextColumn::make('added')
                    ->label('加購')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => (int) $state === 0 ? '0 ⚠️' : (string) $state)
                    ->color(fn ($state) => (int) $state === 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('add_rate_pct')
                    ->label('加購率')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => round((float) $state, 1) . '%')
                    ->color(fn ($state) => (float) $state === 0.0 ? 'danger' : ((float) $state < 3 ? 'warning' : 'success')),
            ])
            ->emptyStateHeading('沒有浪費 — 太棒了')
            ->emptyStateDescription('過去 14 天每個廣告 landing 都至少帶來一次加購。');
    }

    /**
     * Two-phase query: (1) get paid-source landings with session counts,
     * (2) join against cart_events to count "added" sessions per landing.
     * Done in PHP rather than one giant SQL because the cart_events ⨯
     * visits join on session_id needs date-bounded subqueries that
     * are easier to read here.
     */
    private function loadRows(): array
    {
        $start = now()->subDays(14);
        $paidSources = ['google_ads', 'facebook_ads', 'bing_ads', 'tiktok_ads', 'linkedin_ads', 'other_ads'];

        // Subquery: distinct session_ids that triggered add_to_cart in the period.
        // Single round-trip via JOIN avoids hand-pasting a large IN list, which would
        // both balloon the SQL and expose us to escaping bugs.
        $addedSubquery = DB::table('cart_events')
            ->select('session_id')
            ->where('event_type', 'add_to_cart')
            ->where('created_at', '>=', $start)
            ->whereNotNull('session_id')
            ->where('is_internal', false)
            ->groupBy('session_id');

        $rows = DB::table('visits')
            ->select(
                'visits.referer_source as source',
                'visits.landing_path',
                DB::raw('COUNT(DISTINCT visits.session_id) as sessions'),
                DB::raw('COUNT(DISTINCT added.session_id) as added'),
            )
            ->leftJoinSub($addedSubquery, 'added', fn ($j) => $j->on('added.session_id', '=', 'visits.session_id'))
            ->where('visits.visited_at', '>=', $start)
            ->where('visits.is_internal', false)
            ->whereIn('visits.referer_source', $paidSources)
            ->whereNotNull('visits.session_id')
            ->whereNotNull('visits.landing_path')
            ->groupBy('visits.referer_source', 'visits.landing_path')
            ->havingRaw('sessions >= 5') // suppress noise — single-visit landings aren't actionable
            ->orderByDesc('sessions')
            ->limit(20)
            ->get();

        $productSlugs = [];
        foreach ($rows as $r) {
            if (preg_match('~^/products/([^/?#]+)~', $r->landing_path, $m)) {
                $productSlugs[] = urldecode($m[1]);
            }
        }
        $products = Product::whereIn('slug', $productSlugs)->pluck('name', 'slug')->all();

        $out = [];
        foreach ($rows as $i => $r) {
            $sessions = (int) $r->sessions;
            $added = (int) $r->added;
            $title = $this->resolveTitle($r->landing_path, $products);

            $out[] = [
                'id' => (string) $i,
                'rank' => $i + 1,
                'source' => $r->source,
                'landing_path' => $r->landing_path,
                'title' => $title,
                'sessions' => $sessions,
                'added' => $added,
                'add_rate_pct' => $sessions > 0 ? round($added / $sessions * 100, 1) : 0,
            ];
        }
        return $out;
    }

    private function resolveTitle(?string $path, array $products): string
    {
        if (! $path) return '—';
        if ($path === '/' || $path === '') return '首頁';
        if (preg_match('~^/products/([^/?#]+)~', $path, $m)) {
            $slug = urldecode($m[1]);
            return $products[$slug] ?? $slug;
        }
        if (str_starts_with($path, '/bundles/')) return '活動：' . urldecode(str_replace('/bundles/', '', $path));
        if (str_starts_with($path, '/products/category/')) return '分類：' . urldecode(str_replace('/products/category/', '', $path));
        if (str_starts_with($path, '/campaigns/')) return '主題：' . urldecode(str_replace('/campaigns/', '', $path));
        return urldecode($path);
    }
}
