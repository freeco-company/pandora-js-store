<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--base-url= : The base URL for the site (defaults to APP_URL)}';

    protected $description = 'Generate a sitemap.xml with all products and articles';

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('base-url') ?: config('app.url'), '/');

        $this->info("Generating sitemap for: {$baseUrl}");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Static pages
        $staticPages = [
            ['url' => '/',             'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => '/products',     'priority' => '0.9', 'changefreq' => 'daily'],
            ['url' => '/articles',     'priority' => '0.8', 'changefreq' => 'daily'],
            ['url' => '/about',        'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => '/contact',      'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($staticPages as $page) {
            $xml .= $this->buildUrlEntry(
                $baseUrl . $page['url'],
                now()->toDateString(),
                $page['changefreq'],
                $page['priority']
            );
        }

        // Products
        $productCount = 0;
        Product::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->chunk(100, function ($products) use (&$xml, $baseUrl, &$productCount) {
                foreach ($products as $product) {
                    $xml .= $this->buildUrlEntry(
                        $baseUrl . '/products/' . $product->slug,
                        $product->updated_at->toDateString(),
                        'weekly',
                        '0.8'
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
                    $prefix = match ($article->source_type) {
                        'blog'      => '/articles/blog/',
                        'news'      => '/articles/news/',
                        'brand'     => '/articles/brand/',
                        'recommend' => '/articles/recommend/',
                        default     => '/articles/',
                    };

                    $xml .= $this->buildUrlEntry(
                        $baseUrl . $prefix . $article->slug,
                        ($article->published_at ?? $article->updated_at)->toDateString(),
                        'monthly',
                        '0.7'
                    );
                    $articleCount++;
                }
            });

        $xml .= '</urlset>' . "\n";

        // Write to public directory
        $path = public_path('sitemap.xml');
        file_put_contents($path, $xml);

        $this->info("Sitemap generated: {$path}");
        $this->table(
            ['Content', 'Count'],
            [
                ['Static pages', count($staticPages)],
                ['Products', $productCount],
                ['Articles', $articleCount],
                ['Total URLs', count($staticPages) + $productCount + $articleCount],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Build a single <url> XML entry.
     */
    private function buildUrlEntry(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        return "  <url>\n"
            . "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n"
            . "    <lastmod>{$lastmod}</lastmod>\n"
            . "    <changefreq>{$changefreq}</changefreq>\n"
            . "    <priority>{$priority}</priority>\n"
            . "  </url>\n";
    }
}
