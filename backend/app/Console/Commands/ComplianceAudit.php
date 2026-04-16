<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use App\Services\DiscordNotifier;
use App\Services\LegalContentSanitizer;
use Illuminate\Console\Command;

/**
 * Scan every Product + Article for forbidden health-claim terms.
 * Auto-sanitize + append disclaimer when risks are found.
 * Send a Discord summary when DISCORD_COMPLIANCE_WEBHOOK is set.
 */
class ComplianceAudit extends Command
{
    protected $signature = 'compliance:audit
        {--dry-run : Report only, do not modify content}
        {--quiet-webhook : Skip Discord notification even if webhook is configured}';

    protected $description = 'Audit product + article text for 食安法/健食法 compliance, auto-rewrite, notify Discord';

    public function __construct(
        private readonly LegalContentSanitizer $sanitizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? '=== Compliance audit (dry-run) ===' : '=== Compliance audit ===');

        $stats = [
            'products' => $this->auditCollection(Product::query(), 'description', 'product', $dryRun, ['name', 'short_description']),
            'articles' => $this->auditCollection(Article::query()->whereNotNull('content'), 'content', 'article', $dryRun, ['title']),
        ];

        $this->table(
            ['Kind', 'Scanned', 'Flagged', 'Fixed', 'Added Disclaimer', 'Top Terms'],
            [
                ['product', $stats['products']['scanned'], $stats['products']['flagged'], $stats['products']['fixed'], $stats['products']['disclaimer_added'], $this->topTerms($stats['products']['terms'])],
                ['article', $stats['articles']['scanned'], $stats['articles']['flagged'], $stats['articles']['fixed'], $stats['articles']['disclaimer_added'], $this->topTerms($stats['articles']['terms'])],
            ],
        );

        if (! $this->option('quiet-webhook')) {
            $this->notifyDiscord($stats, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * @param  string[]  $alsoSanitizeTextFields  Field names sanitized as plain text (no HTML)
     * @return array{scanned:int,flagged:int,fixed:int,disclaimer_added:int,terms:array<string,int>}
     */
    private function auditCollection($query, string $htmlField, string $kind, bool $dryRun, array $alsoSanitizeTextFields = []): array
    {
        $scanned = 0; $flagged = 0; $fixed = 0; $disclaimerAdded = 0;
        $terms = [];

        foreach ($query->cursor() as $row) {
            $scanned++;
            $html = $row->{$htmlField} ?? '';
            if ($html === '' || $html === null) continue;

            $risks = $this->sanitizer->riskReport($html);

            // Also scan plain-text fields (name / title)
            foreach ($alsoSanitizeTextFields as $f) {
                $val = $row->{$f} ?? '';
                if ($val === '' || $val === null) continue;
                foreach ($this->sanitizer->riskReport($val) as $t) $risks[] = $t;
            }
            $risks = array_values(array_unique($risks));

            $hasDisclaimer = str_contains($html, 'legal-disclaimer');
            $needsFix = ! empty($risks);
            $needsDisclaimer = ! $hasDisclaimer;

            if ($needsFix || $needsDisclaimer) {
                $flagged++;
                foreach ($risks as $t) $terms[$t] = ($terms[$t] ?? 0) + 1;

                if (! $dryRun) {
                    // Rewrite HTML field with sanitize + disclaimer
                    $row->{$htmlField} = $this->sanitizer->process($html, $kind);
                    if (! $hasDisclaimer) $disclaimerAdded++;
                    if ($needsFix) $fixed++;

                    // Sanitize plain-text fields
                    foreach ($alsoSanitizeTextFields as $f) {
                        $val = $row->{$f} ?? '';
                        if ($val !== '' && $val !== null) {
                            $row->{$f} = $this->sanitizer->sanitizeText($val);
                        }
                    }
                    $row->save();
                }
            }
        }

        return compact('scanned', 'flagged', 'fixed', 'disclaimerAdded', 'terms')
            + ['disclaimer_added' => $disclaimerAdded];
    }

    /**
     * Format top 5 terms as "term:count, term:count".
     */
    private function topTerms(array $terms): string
    {
        if (empty($terms)) return '—';
        arsort($terms);
        $top = array_slice($terms, 0, 5, true);
        $out = [];
        foreach ($top as $t => $n) $out[] = "{$t}:{$n}";
        return implode(', ', $out);
    }

    private function notifyDiscord(array $stats, bool $dryRun): void
    {
        $n = DiscordNotifier::compliance();
        if (! $n->isEnabled()) return;

        $productFlagged = $stats['products']['flagged'];
        $articleFlagged = $stats['articles']['flagged'];
        $total = $productFlagged + $articleFlagged;

        $color = $total === 0 ? 0x4A9D5F : ($total > 20 ? 0xE0748C : 0xE8A93B);

        $desc = $dryRun
            ? '📋 乾跑審查（未實際修改）'
            : "📝 自動修正完成：商品 {$stats['products']['fixed']} / 文章 {$stats['articles']['fixed']}";

        $fields = [
            ['name' => '🛍️ 商品', 'value' => "掃描 {$stats['products']['scanned']}、違規 {$stats['products']['flagged']}\n熱門違規: " . $this->topTerms($stats['products']['terms']), 'inline' => true],
            ['name' => '📖 文章', 'value' => "掃描 {$stats['articles']['scanned']}、違規 {$stats['articles']['flagged']}\n熱門違規: " . $this->topTerms($stats['articles']['terms']), 'inline' => true],
        ];

        $n->embed(
            title: "🔍 法規合規審查 · {$total} 筆需修正",
            description: $desc,
            fields: $fields,
            color: $color,
        );
    }
}
