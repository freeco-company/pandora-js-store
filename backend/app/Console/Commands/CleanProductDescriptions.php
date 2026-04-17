<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class CleanProductDescriptions extends Command
{
    protected $signature = 'products:clean-descriptions {--dry : Preview without saving}';
    protected $description = 'Clean WP/Elementor HTML cruft from product descriptions';

    public function handle(): int
    {
        $dry = $this->option('dry');
        $products = Product::whereNotNull('description')
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

                $this->info("#{$product->id} {$product->name}: {$sizeBefore} → {$sizeAfter} bytes (-{$pct}%)");

                if (!$dry) {
                    $product->description = $clean;
                    $product->save();
                }
            }
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Cleaned {$cleaned} / {$products->count()} products");

        // Also clean short_descriptions with HTML
        $shortProducts = Product::whereNotNull('short_description')
            ->where('short_description', 'LIKE', '%<%')
            ->get();

        $shortCleaned = 0;
        foreach ($shortProducts as $product) {
            $original = $product->short_description;
            // For short_description: strip all HTML, keep text lines
            $clean = strip_tags($original);
            $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');
            $clean = preg_replace('/\n{3,}/', "\n\n", $clean);
            $clean = trim($clean);

            if ($clean !== $original) {
                $shortCleaned++;
                if (!$dry) {
                    $product->short_description = $clean;
                    $product->save();
                }
                $this->line("  short_desc #{$product->id}: cleaned HTML tags");
            }
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Cleaned {$shortCleaned} short_descriptions");

        return self::SUCCESS;
    }

    private function cleanHtml(string $html): string
    {
        // 1. Remove Elementor wrapper divs (data-id, data-element_type, data-widget_type)
        $html = preg_replace('/<div[^>]*data-(id|element_type|widget_type|settings)[^>]*>/i', '', $html);

        // 2. Remove empty divs and sections
        $html = preg_replace('/<(div|section|span)\s*class="[^"]*elementor[^"]*"[^>]*>/i', '', $html);

        // 3. Remove Elementor-specific classes from remaining tags
        $html = preg_replace('/\s*class="[^"]*elementor[^"]*"/i', '', $html);

        // 4. Remove inline styles that are WP cruft
        $html = preg_replace('/\s*style="[^"]*font-family[^"]*"/i', '', $html);

        // 5. Remove empty tags
        $html = preg_replace('/<(div|span|p|section)\s*>\s*<\/\1>/i', '', $html);

        // 6. Remove data-* attributes
        $html = preg_replace('/\s*data-[a-z_-]+="[^"]*"/i', '', $html);

        // 7. Clean up excessive closing divs
        // Count opens vs closes and trim excess
        $opens = preg_match_all('/<div/i', $html);
        $closes = preg_match_all('/<\/div>/i', $html);
        if ($closes > $opens) {
            $excess = $closes - $opens;
            for ($i = 0; $i < $excess; $i++) {
                $html = preg_replace('/<\/div>\s*$/i', '', $html, 1);
            }
        }

        // 8. Fix image src paths
        $html = preg_replace('/src="(?!http|\/storage)([^"]+)"/i', 'src="/storage/$1"', $html);

        // 9. Remove images with broken/WP paths (wp-content, etc)
        $html = preg_replace('/<img[^>]*src="[^"]*wp-content[^"]*"[^>]*\/?>/i', '', $html);

        // 10. Collapse whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        $html = trim($html);

        return $html;
    }
}
