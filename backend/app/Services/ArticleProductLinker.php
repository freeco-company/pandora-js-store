<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Scans article HTML, finds product-name mentions, wraps the first occurrence
 * of each product as an internal link, and syncs the article_product pivot.
 *
 * UTF-8 safety: only mb_* + preg_* + str_starts_with are used. Never byte-level
 * str_replace on Chinese text (see CLAUDE.md).
 */
class ArticleProductLinker
{
    private const MIN_NAME_LEN = 3;
    private const LINK_CLASS = 'product-mention text-[#9F6B3E] underline decoration-dotted underline-offset-2 hover:text-[#7a5836]';

    private ?Collection $products = null;
    private ?array $aliasIndex = null;

    public function process(Article $article, bool $dryRun = false): array
    {
        $original = (string) $article->content;
        $content = $original;
        $index = $this->getAliasIndex();

        $mentions = [];
        $linkedIds = [];

        foreach ($index as $entry) {
            $pid = $entry['product_id'];
            $slug = $entry['slug'];
            $alias = $entry['alias'];

            $count = $this->countOccurrencesOutsideAnchors($content, $alias);
            if ($count === 0) {
                continue;
            }

            $mentions[$pid] = ['mention_count' => ($mentions[$pid]['mention_count'] ?? 0) + $count];

            if (in_array($pid, $linkedIds, true)) {
                continue;
            }

            $newContent = $this->wrapFirstMention($content, $alias, $slug);
            if ($newContent !== $content) {
                $content = $newContent;
                $linkedIds[] = $pid;
            }
        }

        $changed = $content !== $original;

        if (! $dryRun) {
            if ($changed) {
                $article->content = $content;
                $article->save();
            }
            $article->products()->sync($mentions);
        }

        return [
            'article_id' => $article->id,
            'mentions' => count($mentions),
            'linked' => count($linkedIds),
            'content_changed' => $changed,
            'matched_product_ids' => array_keys($mentions),
            'newly_linked_ids' => $linkedIds,
        ];
    }

    private function getProducts(): Collection
    {
        if ($this->products !== null) {
            return $this->products;
        }
        return $this->products = Product::visible()
            ->select('id', 'name', 'slug')
            ->get()
            ->values();
    }

    /**
     * Build alias → product index, longest alias first. Product names in DB
     * carry SEO ballast ("JEROSSE 婕樂纖 X 官方授權正品 (N錠/盒)") that never
     * appears verbatim in articles, so we derive cleaner aliases.
     */
    private function getAliasIndex(): array
    {
        if ($this->aliasIndex !== null) {
            return $this->aliasIndex;
        }

        $index = [];
        $seen = []; // alias-string => product_id (first product wins on collision)

        foreach ($this->getProducts() as $product) {
            foreach ($this->deriveAliases((string) $product->name) as $alias) {
                if (mb_strlen($alias) < self::MIN_NAME_LEN) {
                    continue;
                }
                if (isset($seen[$alias])) {
                    continue; // ambiguous alias — first product owns it
                }
                $seen[$alias] = $product->id;
                $index[] = [
                    'product_id' => $product->id,
                    'slug' => $product->slug,
                    'alias' => $alias,
                ];
            }
        }

        usort($index, fn ($a, $b) => mb_strlen($b['alias']) - mb_strlen($a['alias']));
        return $this->aliasIndex = $index;
    }

    /**
     * Strip brand/spec/marketing ballast and split bracketed alt-names into
     * candidate aliases. Returns unique non-empty strings.
     */
    private function deriveAliases(string $name): array
    {
        $aliases = [];

        // Pull out content inside (...) and 【...】 first as alt-names.
        if (preg_match_all('/[(（【\[]([^()（）【】\[\]]+)[)）】\]]/u', $name, $matches)) {
            foreach ($matches[1] as $bracket) {
                foreach (preg_split('/[\/／、,，]/u', $bracket) as $piece) {
                    $piece = trim($piece);
                    if ($piece !== '') {
                        $aliases[] = $piece;
                    }
                }
            }
        }

        // Strip brand & marketing tokens to expose the core name.
        $core = preg_replace([
            '/JEROSSE\s*/iu',
            '/Jerosse\s*/u',
            '/婕樂纖\s*/u',
            '/官方授權正品/u',
            '/官方授權/u',
            '/健康食品認證/u',
            '/口服保養推薦/u',
            '/喝水神器/u',
            '/\d+\s*(顆|錠|包|份|入|盒|瓶)\s*\/?\s*(盒|瓶)?/u',
            '/[\p{Han}]{2,4}代言/u',
            '/[(（【\[][^()（）【】\[\]]*[)）】\]]/u',
        ], '', $name);

        $core = trim((string) $core);
        foreach (preg_split('/[\s\/／、，,\-－]+/u', $core) as $piece) {
            $piece = trim($piece);
            if ($piece !== '') {
                $aliases[] = $piece;
            }
        }

        $aliases[] = $name;

        $aliases = array_filter($aliases, function ($s) {
            if ($s === '') {
                return false;
            }
            // Drop pure spec strings like "60錠", "14包", "120顆".
            if (preg_match('/^\d+\s*(顆|錠|包|份|入|盒|瓶|片|粒|杯|ml|g)$/iu', $s)) {
                return false;
            }
            // Drop standalone unit/marketing words.
            if (in_array($s, ['盒', '瓶', '官方授權', '官方授權正品', '健康食品認證', '婕樂纖', 'JEROSSE'], true)) {
                return false;
            }
            return true;
        });

        return array_values(array_unique($aliases));
    }

    /**
     * Count occurrences of $needle in text segments only — skips anything
     * inside <a>...</a> blocks and HTML tag attributes.
     */
    private function countOccurrencesOutsideAnchors(string $html, string $needle): int
    {
        $total = 0;
        foreach ($this->splitByAnchors($html) as $part) {
            if ($this->isAnchor($part)) {
                continue;
            }
            foreach ($this->splitByTags($part) as $tok) {
                if (str_starts_with($tok, '<')) {
                    continue;
                }
                $offset = 0;
                while (($pos = mb_strpos($tok, $needle, $offset)) !== false) {
                    $total++;
                    $offset = $pos + mb_strlen($needle);
                }
            }
        }
        return $total;
    }

    private function wrapFirstMention(string $html, string $needle, string $slug): string
    {
        $url = "/products/{$slug}";
        $linkHtml = '<a href="' . $url . '" class="' . self::LINK_CLASS . '">' . htmlspecialchars($needle, ENT_QUOTES, 'UTF-8') . '</a>';

        $parts = $this->splitByAnchors($html);
        $done = false;

        foreach ($parts as $i => $part) {
            if ($done) {
                break;
            }
            if ($this->isAnchor($part)) {
                continue;
            }
            $newPart = $this->replaceFirstInTextNodes($part, $needle, $linkHtml);
            if ($newPart !== $part) {
                $parts[$i] = $newPart;
                $done = true;
            }
        }

        return implode('', $parts);
    }

    private function replaceFirstInTextNodes(string $segment, string $needle, string $replacement): string
    {
        $tokens = $this->splitByTags($segment);
        foreach ($tokens as $i => $tok) {
            if (str_starts_with($tok, '<')) {
                continue;
            }
            $pos = mb_strpos($tok, $needle);
            if ($pos === false) {
                continue;
            }
            $tokens[$i] = mb_substr($tok, 0, $pos) . $replacement . mb_substr($tok, $pos + mb_strlen($needle));
            return implode('', $tokens);
        }
        return $segment;
    }

    private function splitByAnchors(string $html): array
    {
        $parts = preg_split('/(<a\b[^>]*>.*?<\/a>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        return $parts === false ? [$html] : $parts;
    }

    private function splitByTags(string $segment): array
    {
        $tokens = preg_split('/(<[^>]+>)/', $segment, -1, PREG_SPLIT_DELIM_CAPTURE);
        return $tokens === false ? [$segment] : $tokens;
    }

    private function isAnchor(string $part): bool
    {
        return str_starts_with($part, '<a ') || str_starts_with($part, '<a>');
    }
}
