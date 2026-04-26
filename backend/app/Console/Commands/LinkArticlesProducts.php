<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ArticleProductLinker;
use Illuminate\Console\Command;

class LinkArticlesProducts extends Command
{
    protected $signature = 'content:link-articles-products
        {--all : Process all published articles}
        {--article= : Process a single article ID}
        {--since= : Process articles updated within the last N hours (default 24)}
        {--dry : Dry run — print what would change but do not write}';

    protected $description = 'Auto-link product mentions in article bodies and sync the article_product pivot.';

    public function handle(ArticleProductLinker $linker): int
    {
        $query = Article::query()->where('status', 'published');

        if ($id = $this->option('article')) {
            $query->where('id', $id);
        } elseif ($this->option('all')) {
            // no-op — process everything
        } else {
            $hours = (int) ($this->option('since') ?: 24);
            $query->where('updated_at', '>=', now()->subHours($hours));
        }

        $total = (clone $query)->count();
        $dry = (bool) $this->option('dry');

        $this->info(sprintf(
            '[%s] Processing %d article(s)...',
            $dry ? 'DRY RUN' : 'LIVE',
            $total
        ));

        $stats = ['changed' => 0, 'untouched' => 0, 'mentions' => 0, 'links_added' => 0];
        $samples = [];

        $query->orderBy('id')->chunkById(50, function ($articles) use ($linker, $dry, &$stats, &$samples) {
            foreach ($articles as $article) {
                $r = $linker->process($article, $dry);
                $stats['mentions'] += $r['mentions'];
                $stats['links_added'] += $r['linked'];

                if ($r['content_changed']) {
                    $stats['changed']++;
                    if (count($samples) < 5) {
                        $samples[] = sprintf(
                            '  - #%d %s → linked %d product(s): %s',
                            $article->id,
                            mb_substr($article->title, 0, 30),
                            $r['linked'],
                            implode(',', $r['newly_linked_ids'])
                        );
                    }
                } else {
                    $stats['untouched']++;
                }
            }
        });

        $this->newLine();
        $this->info('--- Summary ---');
        $this->line("Articles changed     : {$stats['changed']}");
        $this->line("Articles untouched   : {$stats['untouched']}");
        $this->line("Total product mentions: {$stats['mentions']}");
        $this->line("Total links added    : {$stats['links_added']}");

        if ($samples) {
            $this->newLine();
            $this->info('Sample changes:');
            foreach ($samples as $s) {
                $this->line($s);
            }
        }

        if ($dry) {
            $this->newLine();
            $this->comment('(dry run — no changes written. Re-run without --dry to apply.)');
        }

        return self::SUCCESS;
    }
}
