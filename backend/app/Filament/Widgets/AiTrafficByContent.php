<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "AI 引用排行 — 過去 14 天哪些頁面最常被 AI 抓"
 *
 * Aggregates ai_visits_by_path → groups by path, classifies into content
 * type (home / product / article / category / other), resolves
 * product/article name, returns top 20 by hits.
 *
 * Tells the marketing/content team which pages the AI engines (Claude,
 * ChatGPT, Perplexity) are actually pulling — the only meaningful KPI
 * for GEO (generative engine optimization) work.
 */
class AiTrafficByContent extends BaseWidget
{
    protected static ?int $sort = 11;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'AI 引用排行（近 14 天 Top 20）';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => Cache::remember(
                'ai_traffic:by_content:14d',
                now()->addMinutes(15),
                fn () => $this->loadRows()
            ))
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('#')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('content_type')
                    ->label('類型')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'product' => 'success',
                        'article' => 'info',
                        'home' => 'primary',
                        'category' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'product' => '商品',
                        'article' => '文章',
                        'home' => '首頁',
                        'category' => '分類',
                        default => '其他',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('頁面標題 / 路徑')
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('path')
                    ->label('路徑')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('hits')
                    ->label('AI 點閱')
                    ->alignEnd()
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => number_format((int) $state)),

                Tables\Columns\TextColumn::make('bots')
                    ->label('來源 Bot')
                    ->color('gray'),
            ])
            ->emptyStateHeading('尚無 AI 引用資料')
            ->emptyStateDescription('過去 14 天還沒有偵測到 AI bot 訪問記錄。');
    }

    private function loadRows(): array
    {
        $rows = DB::table('ai_visits_by_path')
            ->select('path', DB::raw('SUM(hits) as total'), DB::raw("GROUP_CONCAT(DISTINCT bot_type ORDER BY bot_type) as bots"))
            ->where('date', '>=', now()->subDays(14)->toDateString())
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $productSlugs = [];
        $articleSlugs = [];
        foreach ($rows as $r) {
            // 用 `~` 當 delimiter，避免 character class 內的 `#` 跟 delimiter 撞
            if (preg_match('~^/products/([^/?#]+)~', $r->path, $m)) {
                $productSlugs[] = $m[1];
            } elseif (preg_match('~^/articles/([^/?#]+)~', $r->path, $m)) {
                $articleSlugs[] = $m[1];
            }
        }
        $products = Product::whereIn('slug', $productSlugs)->pluck('name', 'slug')->all();
        $articles = Article::whereIn('slug', $articleSlugs)->pluck('title', 'slug')->all();

        $out = [];
        foreach ($rows as $i => $r) {
            $type = $this->classifyPath($r->path);
            $title = $this->resolveTitle($r->path, $type, $products, $articles);
            $out[] = [
                'id' => (string) $i, // Filament TableWidget::getTableRecordKey() 簽名要 string，不能回 int/null
                'rank' => $i + 1,
                'content_type' => $type,
                'title' => $title,
                'path' => $r->path,
                'hits' => (int) $r->total,
                'bots' => str_replace(',', ' · ', (string) $r->bots),
            ];
        }
        return $out;
    }

    private function classifyPath(string $path): string
    {
        if ($path === '/' || $path === '') {
            return 'home';
        }
        if (str_starts_with($path, '/products/')) {
            return 'product';
        }
        if (str_starts_with($path, '/articles/')) {
            return 'article';
        }
        if (str_starts_with($path, '/category/') || str_starts_with($path, '/categories/')) {
            return 'category';
        }
        return 'other';
    }

    private function resolveTitle(string $path, string $type, array $products, array $articles): string
    {
        if ($type === 'home') {
            return '首頁';
        }
        // Use ~ as delimiter so we can keep `#` literal inside the char class
        // (the previous `#…[^/?#]…#` pattern collided with the delimiter and
        // threw "preg_match(): Unknown modifier ']'" on any path containing a
        // fragment segment).
        if ($type === 'product' && preg_match('~^/products/([^/?#]+)~', $path, $m)) {
            return $products[$m[1]] ?? $m[1];
        }
        if ($type === 'article' && preg_match('~^/articles/([^/?#]+)~', $path, $m)) {
            return $articles[$m[1]] ?? $m[1];
        }
        return $path;
    }
}
