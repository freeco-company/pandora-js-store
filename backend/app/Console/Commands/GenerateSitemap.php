<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--base-url= : The base URL for the site (defaults to APP_URL)}';

    protected $description = 'Generate a sitemap.xml with all products, articles, and categories (image sitemap included)';

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('base-url') ?: config('app.url'), '/');

        $this->info("Generating sitemap for: {$baseUrl}");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n"
              . '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        // Static pages
        $staticPages = [
            ['url' => '/',             'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => '/products',     'priority' => '0.9', 'changefreq' => 'daily'],
            ['url' => '/articles',     'priority' => '0.8', 'changefreq' => 'daily'],
            ['url' => '/about',        'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => '/faq',          'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => '/join',         'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => '/return-policy','priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/privacy',      'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/terms',        'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        foreach ($staticPages as $page) {
            $xml .= $this->buildUrlEntry(
                $baseUrl . $page['url'],
                now()->toDateString(),
                $page['changefreq'],
                $page['priority']
            );
        }

        // Product categories — drop 未分類 and any empty categories
        $categoryCount = 0;
        ProductCategory::query()
            ->where('name', '!=', '未分類')
            ->withCount('products')
            ->orderBy('sort_order')
            ->chunk(100, function ($categories) use (&$xml, $baseUrl, &$categoryCount) {
                foreach ($categories as $cat) {
                    if ($cat->products_count === 0) continue;
                    $xml .= $this->buildUrlEntry(
                        $baseUrl . '/products/category/' . $cat->slug,
                        $cat->updated_at?->toDateString() ?? now()->toDateString(),
                        'weekly',
                        '0.7'
                    );
                    $categoryCount++;
                }
            });

        // Products — include image:image entries for Google image search.
        // visible() excludes products whose campaigns are all past/future.
        $productCount = 0;
        Product::visible()
            ->orderBy('updated_at', 'desc')
            ->chunk(100, function ($products) use (&$xml, $baseUrl, &$productCount) {
                foreach ($products as $product) {
                    $images = $this->collectProductImages($product, $baseUrl);
                    $xml .= $this->buildUrlEntry(
                        $baseUrl . '/products/' . $product->slug,
                        $product->updated_at->toDateString(),
                        'weekly',
                        '0.8',
                        $images,
                        $product->name,
                    );
                    $productCount++;
                }
            });

        // Articles
        $articleCount = 0;
        Article::where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->chunk(100, function ($articles) use (&$xml, $baseUrl, &$articleCount) {
                foreach ($articles as $article) {
                    $images = $article->image
                        ? [$this->absoluteImageUrl($article->image, $baseUrl)]
                        : [];
                    $xml .= $this->buildUrlEntry(
                        $baseUrl . '/articles/' . $article->slug,
                        ($article->published_at ?? $article->updated_at)->toDateString(),
                        'monthly',
                        '0.7',
                        $images,
                        $article->title,
                    );
                    $articleCount++;
                }
            });

        $xml .= '</urlset>' . "\n";

        $path = public_path('sitemap.xml');
        file_put_contents($path, $xml);

        $this->info("Sitemap generated: {$path}");
        $this->table(
            ['Content', 'Count'],
            [
                ['Static pages', count($staticPages)],
                ['Categories',   $categoryCount],
                ['Products',     $productCount],
                ['Articles',     $articleCount],
                ['Total URLs',   count($staticPages) + $categoryCount + $productCount + $articleCount],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Collect absolute URLs for a product's primary image and gallery.
     * Returns up to 10 images (Google's effective cap per URL).
     */
    private function collectProductImages(Product $product, string $baseUrl): array
    {
        $urls = [];
        if ($product->image) {
            $urls[] = $this->absoluteImageUrl($product->image, $baseUrl);
        }
        foreach ((array) ($product->gallery ?? []) as $path) {
            if (! is_string($path) || $path === '') continue;
            $urls[] = $this->absoluteImageUrl($path, $baseUrl);
            if (count($urls) >= 10) break;
        }
        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Normalize stored paths ("/storage/foo.jpg" or "foo.jpg") into an absolute URL.
     */
    private function absoluteImageUrl(string $path, string $baseUrl): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            return $baseUrl . $path;
        }
        return $baseUrl . '/storage/' . ltrim($path, '/');
    }

    /**
     * Build a single <url> XML entry with optional <image:image> children.
     */
    private function buildUrlEntry(
        string $loc,
        string $lastmod,
        string $changefreq,
        string $priority,
        array $images = [],
        ?string $imageCaption = null,
    ): string {
        $xml = "  <url>\n"
            . "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n"
            . "    <lastmod>{$lastmod}</lastmod>\n"
            . "    <changefreq>{$changefreq}</changefreq>\n"
            . "    <priority>{$priority}</priority>\n";

        // Emit image:caption only on the first image to avoid Google flagging
        // repeated captions as keyword-stuffed spam across gallery entries.
        foreach ($images as $index => $img) {
            $xml .= "    <image:image>\n"
                . "      <image:loc>" . htmlspecialchars($img, ENT_XML1, 'UTF-8') . "</image:loc>\n";
            if ($imageCaption && $index === 0) {
                $xml .= "      <image:caption>" . htmlspecialchars($imageCaption, ENT_XML1, 'UTF-8') . "</image:caption>\n";
            }
            $xml .= "    </image:image>\n";
        }

        $xml .= "  </url>\n";
        return $xml;
    }
}
