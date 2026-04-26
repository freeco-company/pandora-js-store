<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use App\Services\ArticleProductLinker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Apply a rewrite batch JSON to the DB. Validates required fields per row,
 * stamps rewritten_at, and (for articles) re-runs ArticleProductLinker so
 * the new content gets product mentions linked again.
 */
class ImportRewrite extends Command
{
    protected $signature = 'content:import-rewrite
        {file : Path to the rewrite batch JSON}
        {--dry : Validate + preview, do not write}
        {--no-relink : Articles only — skip re-running ArticleProductLinker}';

    protected $description = 'Import a rewritten batch JSON back into the DB.';

    public function handle(ArticleProductLinker $linker): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($file), true);
        if (! is_array($rows) || empty($rows)) {
            $this->error('JSON parse failed or empty payload.');
            return self::FAILURE;
        }

        $type = $this->detectType($rows[0]);
        if ($type === null) {
            $this->error('Could not detect type — first row missing rewritten_content / rewritten_description.');
            return self::FAILURE;
        }

        $this->info("Detected type: {$type}");
        $this->info(sprintf('[%s] Importing %d row(s)...', $this->option('dry') ? 'DRY' : 'LIVE', count($rows)));

        $applied = 0;
        $skipped = 0;
        $marked_skip = 0;
        $relinked = 0;

        foreach ($rows as $i => $row) {
            // Agent-marked skip — record skip_reason in DB by stamping
            // rewritten_at without modifying content. Prevents the row
            // from being picked up again on the next export.
            if (! empty($row['skip_reason'])) {
                if (! $this->option('dry')) {
                    DB::transaction(function () use ($type, $row) {
                        if ($type === 'article') {
                            Article::where('id', $row['id'])->update(['rewritten_at' => now()]);
                        } else {
                            Product::where('id', $row['id'])->update(['rewritten_at' => now()]);
                        }
                    });
                }
                $this->line("  [skip-mark #{$row['id']}] {$row['skip_reason']}");
                $marked_skip++;
                continue;
            }

            $err = $this->validate($type, $row);
            if ($err !== null) {
                $this->warn("  [reject #{$row['id']}] {$err}");
                $skipped++;
                continue;
            }

            if ($this->option('dry')) {
                $applied++;
                continue;
            }

            DB::transaction(function () use ($type, $row, $linker, &$relinked) {
                if ($type === 'article') {
                    $a = Article::find($row['id']);
                    if (! $a) {
                        return;
                    }
                    $a->title = $row['rewritten_title'] ?? $a->title;
                    $a->excerpt = $row['rewritten_excerpt'] ?? $a->excerpt;
                    $a->content = $row['rewritten_content'];
                    $a->rewritten_at = now();
                    $a->save();

                    if (! $this->option('no-relink')) {
                        $linker->process($a);
                        $relinked++;
                    }
                } else {
                    $p = Product::find($row['id']);
                    if (! $p) {
                        return;
                    }
                    $p->short_description = $row['rewritten_short_description'] ?? $p->short_description;
                    $p->description = $row['rewritten_description'];
                    $p->rewritten_at = now();
                    $p->save();
                }
            });

            $applied++;
        }

        if (! $this->option('dry')) {
            $this->bumpCacheVersion($type);
            Cache::flush(); // Easier than ferreting out per-controller version keys.
        }

        $this->newLine();
        $this->info('--- Summary ---');
        $this->line("Applied         : {$applied}");
        $this->line("Marked-as-skip  : {$marked_skip}");
        $this->line("Rejected        : {$skipped}");
        if ($type === 'article' && ! $this->option('no-relink')) {
            $this->line("Re-linked       : {$relinked}");
        }

        if ($this->option('dry')) {
            $this->comment('(dry run — no DB writes. Re-run without --dry.)');
        }

        return self::SUCCESS;
    }

    private function detectType(array $row): ?string
    {
        if (array_key_exists('rewritten_content', $row)) {
            return 'article';
        }
        if (array_key_exists('rewritten_description', $row)) {
            return 'product';
        }
        return null;
    }

    private function validate(string $type, array $row): ?string
    {
        if (! isset($row['id']) || ! is_int($row['id'])) {
            return 'missing or non-int id';
        }

        if ($type === 'article') {
            if (empty($row['rewritten_content'])) {
                return 'rewritten_content is empty';
            }
            if (mb_strlen($row['rewritten_content']) < 200) {
                return 'rewritten_content too short (<200 chars)';
            }
        } else {
            if (empty($row['rewritten_description'])) {
                return 'rewritten_description is empty';
            }
            if (mb_strlen($row['rewritten_description']) < 100) {
                return 'rewritten_description too short (<100 chars)';
            }
        }

        return null;
    }

    private function bumpCacheVersion(string $type): void
    {
        $key = $type === 'article' ? 'articles:cache_version' : 'products:cache_version';
        Cache::forever($key, (int) Cache::get($key, 0) + 1);
    }
}
