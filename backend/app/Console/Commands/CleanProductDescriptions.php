<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class CleanProductDescriptions extends Command
{
    protected $signature = 'products:clean-descriptions {--dry : Preview without saving}';
    protected $description = 'Deep-clean WP/Elementor HTML cruft from product descriptions';

    public function handle(): int
    {
        $dry = $this->option('dry');
        $products = Product::where('is_active', true)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->get();

        $cleaned = 0;
        foreach ($products as $product) {
            $original = $product->description;
            $clean = $this->cleanHtml($original);

            if ($clean !== $original) {
                $cleaned++;
                $sizeBefore = strlen($original);
                $sizeAfter = strlen($clean);
                $pct = round((1 - $sizeAfter / max($sizeBefore, 1)) * 100);
                $this->info("#{$product->id} " . mb_substr($product->name, 0, 25) . ": {$sizeBefore} → {$sizeAfter} (-{$pct}%)");

                if (!$dry) {
                    $product->description = $clean;
                    $product->save();
                }
            }
        }

        $this->info(($dry ? '[DRY] ' : '') . "Cleaned {$cleaned} / {$products->count()} descriptions");

        // Also clean short_descriptions with HTML
        $shortProducts = Product::where('is_active', true)
            ->whereNotNull('short_description')
            ->where('short_description', 'LIKE', '%<%')
            ->get();

        $shortCleaned = 0;
        foreach ($shortProducts as $product) {
            $original = $product->short_description;
            $clean = strip_tags($original);
            $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');
            $clean = str_replace(["\xc2\xa0", 'Â'], '', $clean);
            $clean = preg_replace('/\n{3,}/', "\n\n", $clean);
            $clean = trim($clean);
            if ($clean !== $original && !$dry) {
                $product->short_description = $clean;
                $product->save();
                $shortCleaned++;
            }
        }
        $this->info(($dry ? '[DRY] ' : '') . "Cleaned {$shortCleaned} short_descriptions");

        if (!$dry) {
            \Illuminate\Support\Facades\Cache::flush();
            $this->info('Cache flushed');
        }

        return self::SUCCESS;
    }

    private function cleanHtml(string $html): string
    {
        // ── Strip wrapper elements ──
        // Remove ALL divs (open + close)
        $html = preg_replace('/<div[^>]*>/i', '', $html);
        $html = preg_replace('/<\/div>/i', '', $html);

        // Remove <section> wrappers
        $html = preg_replace('/<section[^>]*>/i', '', $html);
        $html = preg_replace('/<\/section>/i', '', $html);

        // ── Clean attributes ──
        // Remove ALL inline styles
        $html = preg_replace('/\s*style\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace("/\s*style\s*=\s*'[^']*'/i", '', $html);

        // Remove ALL class attributes
        $html = preg_replace('/\s*class="[^"]*"/i', '', $html);

        // Remove data-* attributes
        $html = preg_replace('/\s*data-[a-z_-]+="[^"]*"/i', '', $html);

        // ── Fix heading hierarchy ──
        // H4 → H3
        $html = preg_replace('/<h4([^>]*)>/i', '<h3$1>', $html);
        $html = preg_replace('/<\/h4>/i', '</h3>', $html);

        // ── Remove junk elements ──
        // Empty tags
        $html = preg_replace('/<(p|span|h[1-6]|li)\s*>\s*<\/\1>/i', '', $html);
        $html = preg_replace('/<p>\s*(&nbsp;|\xc2\xa0|Â)\s*<\/p>/i', '', $html);
        $html = preg_replace('/<p>\s*(<br\s*\/?>)+\s*<\/p>/i', '', $html);

        // Unwrap empty spans
        $html = preg_replace('/<span\s*>([^<]*)<\/span>/', '$1', $html);

        // Remove <hr>
        $html = preg_replace('/<hr\s*\/?>/i', '', $html);

        // Remove &nbsp; / Â
        $html = str_replace(["\xc2\xa0", 'Â'], '', $html);
        $html = preg_replace('/&nbsp;/', ' ', $html);

        // ── Fix links ──
        // Old WP product links /product/ → /products/
        $html = preg_replace('#href="[^"]*?/product/#', 'href="/products/', $html);

        // Remove external links but keep text
        $html = preg_replace('#<a[^>]*href="https?://jerosse\.com\.tw[^"]*"[^>]*>(.*?)</a>#is', '$1', $html);
        $html = preg_replace('#<a[^>]*href="https?://i\.ytimg[^"]*"[^>]*>.*?</a>#is', '', $html);

        // ── Remove media junk ──
        // YouTube embeds
        $html = preg_replace('/<img[^>]*ytimg[^>]*\/?>/i', '', $html);
        $html = preg_replace('/<button[^>]*>.*?<\/button>/is', '', $html);
        $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $html);

        // Images linking to external sites
        $html = preg_replace('#<a[^>]*href="https?://(?!pandora)[^"]*"[^>]*>\s*<img[^>]*>\s*</a>#is', '', $html);

        // wp-content images
        $html = preg_replace('/<img[^>]*src="[^"]*wp-content[^"]*"[^>]*\/?>/i', '', $html);

        // ── Wrap bare text in <p> ──
        $html = str_replace("\r\n", "\n", $html);
        $lines = explode("\n", $html);
        $result = [];
        $buffer = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($buffer !== '') {
                    $result[] = '<p>' . trim($buffer) . '</p>';
                    $buffer = '';
                }
                continue;
            }
            // Line starts with block-level tag → flush buffer, keep as-is
            if (preg_match('/^<(h[1-6]|p |p>|ul|ol|li|blockquote|table|img |a\s)/i', $trimmed)
                || preg_match('/^<\/(blockquote|ul|ol|table|p)>/i', $trimmed)) {
                if ($buffer !== '') {
                    $result[] = '<p>' . trim($buffer) . '</p>';
                    $buffer = '';
                }
                $result[] = $line;
            } else {
                // Plain text → accumulate with <br>
                $buffer .= ($buffer ? '<br>' : '') . $trimmed;
            }
        }
        if ($buffer !== '') {
            $result[] = '<p>' . trim($buffer) . '</p>';
        }

        $html = implode("\n", $result);

        // Final cleanup: remove empty <p> again (may have been created)
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html);
        $html = preg_replace('/<p>\s*(<br\s*\/?>)+\s*<\/p>/i', '', $html);

        // Collapse whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        $html = trim($html);

        return $html;
    }
}
