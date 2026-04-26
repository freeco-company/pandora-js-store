<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Dump a batch of un-rewritten articles or products to a JSON file. The
 * LLM (Claude in chat) reads the file, fills in `rewritten_*` fields, and
 * the import command pushes the result back to the DB.
 *
 * Output path: storage/app/rewrite-batches/{type}-{timestamp}.json
 */
class ExportRewriteBatch extends Command
{
    protected $signature = 'content:export-rewrite-batch
        {--type=article : article|product}
        {--bucket= : Article only — blog|news|brand|recommend (default: all)}
        {--limit=10 : Max items}
        {--prioritize-pivot : Articles only — order by product_count desc (most-mentioned first)}';

    protected $description = 'Dump a batch of un-rewritten articles/products to a JSON file for LLM rewrite.';

    public function handle(): int
    {
        $type = $this->option('type');
        $limit = (int) $this->option('limit');

        return match ($type) {
            'article' => $this->exportArticles($limit),
            'product' => $this->exportProducts($limit),
            default => $this->fail("Unknown type: {$type}. Use article|product."),
        };
    }

    private function exportArticles(int $limit): int
    {
        $q = Article::query()
            ->where('status', 'published')
            ->whereNull('rewritten_at');

        if ($bucket = $this->option('bucket')) {
            $q->whereIn('source_type', explode(',', $bucket));
        }

        if ($this->option('prioritize-pivot')) {
            $q->withCount('products')->orderByDesc('products_count');
        } else {
            $q->orderBy('id');
        }

        $items = $q->limit($limit)->get(['id', 'title', 'slug', 'excerpt', 'content', 'source_type']);

        $payload = $items->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'slug' => $a->slug,
            'source_type' => $a->source_type,
            'original_excerpt' => $a->excerpt,
            'original_content' => $a->content,
            // Filled in by Claude:
            'rewritten_title' => null,
            'rewritten_excerpt' => null,
            'rewritten_content' => null,
        ])->all();

        $path = $this->writeBatch('article', $payload);
        $this->info("Exported {$items->count()} article(s) to {$path}");
        $this->line("Remaining un-rewritten articles: " . Article::whereNull('rewritten_at')->count());
        return self::SUCCESS;
    }

    private function exportProducts(int $limit): int
    {
        $items = Product::query()
            ->whereNull('rewritten_at')
            ->where('is_active', true)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'short_description', 'description', 'hf_cert_claim']);

        $payload = $items->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'hf_cert_claim' => $p->hf_cert_claim,
            'original_short_description' => $p->short_description,
            'original_description' => $p->description,
            // Filled in by Claude:
            'rewritten_short_description' => null,
            'rewritten_description' => null,
        ])->all();

        $path = $this->writeBatch('product', $payload);
        $this->info("Exported {$items->count()} product(s) to {$path}");
        $this->line("Remaining un-rewritten products: " . Product::whereNull('rewritten_at')->count());
        return self::SUCCESS;
    }

    private function writeBatch(string $type, array $payload): string
    {
        $stamp = now()->format('Ymd-His');
        $rel = "rewrite-batches/{$type}-{$stamp}.json";
        Storage::disk('local')->put($rel, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return Storage::disk('local')->path($rel);
    }
}
