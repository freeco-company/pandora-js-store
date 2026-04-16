<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Services\LegalContentSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScrapeJerosseArticles extends Command
{
    protected $signature = 'scrape:jerosse {--type=all : Content type to scrape (blog, news, brand, recommend, or all)} {--clear : Delete all existing articles before scraping}';

    protected $description = 'Scrape articles from jerosse.com.tw sitemaps and import them into the database';

    /**
     * Sitemap URLs keyed by source type.
     */
    private const SITEMAPS = [
        'blog'      => 'https://jerosse.com.tw/blog-sitemap.xml',
        'news'      => 'https://jerosse.com.tw/news-sitemap.xml',
        'brand'     => 'https://jerosse.com.tw/brand-sitemap.xml',
        'recommend' => 'https://jerosse.com.tw/recommend-sitemap.xml',
    ];

    /**
     * Category slug mapping per source type.
     * Keys are URL segments / keywords that help detect the category.
     */
    private const CATEGORY_MAP = [
        'blog' => [
            'health-vitality-column'  => ['health', 'vitality', '健康', '活力', '養生'],
            'beauty-care-column'      => ['beauty', 'care', 'skin', '美容', '保養', '護膚'],
        ],
        'news' => [
            'event-message'   => ['event-message', '活動訊息'],
            'upcoming-events' => ['upcoming', '最新消息', '即將'],
            'fake'            => ['fake', '假冒', '仿冒', '山寨'],
        ],
        'brand' => [
            'award-recognition'       => ['award', '獎', '榮獲'],
            'media-coverage-affirmed' => ['media', 'coverage', '媒體', '報導'],
            'activity-highlights'     => ['activity', 'highlight', '活動', '花絮'],
        ],
        'recommend' => [
            'kol-recommendation'             => ['kol', '部落客', 'blogger'],
            'ordinary-people-recommendation' => ['ordinary', '素人', '一般人'],
            'program-recommendation'         => ['program', '節目', '電視'],
            'celebrity-recommendation'       => ['celebrity', '名人', '藝人', '明星'],
            'magazine-recommendation'        => ['magazine', '雜誌'],
        ],
    ];

    /**
     * Human-readable names for categories.
     */
    private const CATEGORY_NAMES = [
        'health-vitality-column'         => '健康活力專欄',
        'beauty-care-column'             => '美容保養專欄',
        'event-message'                  => '活動訊息',
        'upcoming-events'                => '最新消息',
        'fake'                           => '仿冒品公告',
        'award-recognition'              => '獎項肯定',
        'media-coverage-affirmed'        => '媒體報導',
        'activity-highlights'            => '活動花絮',
        'kol-recommendation'             => 'KOL推薦',
        'ordinary-people-recommendation' => '素人推薦',
        'program-recommendation'         => '節目推薦',
        'celebrity-recommendation'       => '名人推薦',
        'magazine-recommendation'        => '雜誌推薦',
    ];

    private int $articlesImported = 0;
    private int $imagesDownloaded = 0;
    private int $errors = 0;
    private int $sanitized = 0;

    public function __construct(private readonly LegalContentSanitizer $sanitizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');

        if ($type !== 'all' && ! array_key_exists($type, self::SITEMAPS)) {
            $this->error("Invalid type: {$type}. Must be one of: all, blog, news, brand, recommend.");
            return self::FAILURE;
        }

        // Clear existing articles if requested
        if ($this->option('clear')) {
            $this->warn('Clearing all existing articles...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('article_article_category')->truncate();
            Article::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info('All articles cleared.');
        }

        // Ensure categories exist
        $this->ensureCategories();

        $types = $type === 'all' ? array_keys(self::SITEMAPS) : [$type];

        foreach ($types as $sourceType) {
            $this->info('');
            $this->info("=== Scraping {$sourceType} articles ===");
            $this->scrapeType($sourceType);
        }

        $this->newLine();
        $this->info('=== Scraping Complete ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Articles imported', $this->articlesImported],
                ['Images downloaded', $this->imagesDownloaded],
                ['Sanitized (legal)', $this->sanitized],
                ['Errors', $this->errors],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Ensure all article categories exist in the database.
     */
    private function ensureCategories(): void
    {
        foreach (self::CATEGORY_MAP as $type => $categories) {
            foreach ($categories as $slug => $keywords) {
                ArticleCategory::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => self::CATEGORY_NAMES[$slug] ?? Str::title(str_replace('-', ' ', $slug)),
                        'type' => $type,
                    ]
                );
            }
        }

        $this->info('Article categories ensured.');
    }

    /**
     * Scrape all articles for a given source type.
     */
    private function scrapeType(string $sourceType): void
    {
        $sitemapUrl = self::SITEMAPS[$sourceType];

        $this->info("Fetching sitemap: {$sitemapUrl}");

        $urls = $this->parseSitemap($sitemapUrl);

        if (empty($urls)) {
            $this->warn("No URLs found in sitemap for {$sourceType}.");
            return;
        }

        $this->info("Found " . count($urls) . " URLs.");

        // Ensure storage directory exists
        Storage::disk('public')->makeDirectory("articles/{$sourceType}");

        $bar = $this->output->createProgressBar(count($urls));
        $bar->start();

        foreach ($urls as $urlData) {
            try {
                $this->scrapeArticle($urlData['url'], $urlData['lastmod'], $sourceType);
            } catch (\Throwable $e) {
                $this->errors++;
                $this->newLine();
                $this->error("  Error scraping {$urlData['url']}: {$e->getMessage()}");
            }

            $bar->advance();

            // Rate limiting: 1 second delay between requests
            sleep(1);
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Parse a sitemap XML and return an array of [url, lastmod] entries.
     */
    private function parseSitemap(string $sitemapUrl): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'PandoraBot/1.0 (article importer)',
        ])->timeout(30)->get($sitemapUrl);

        if (! $response->successful()) {
            $this->error("Failed to fetch sitemap: HTTP {$response->status()}");
            return [];
        }

        $xml = $response->body();

        // Suppress XML errors and parse
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        libxml_clear_errors();

        $urls = [];
        $urlElements = $doc->getElementsByTagName('url');

        foreach ($urlElements as $urlElement) {
            $loc = '';
            $lastmod = null;

            foreach ($urlElement->childNodes as $child) {
                if ($child->nodeName === 'loc') {
                    $loc = trim($child->textContent);
                } elseif ($child->nodeName === 'lastmod') {
                    $lastmod = trim($child->textContent);
                }
            }

            if ($loc) {
                $urls[] = [
                    'url'     => $loc,
                    'lastmod' => $lastmod,
                ];
            }
        }

        return $urls;
    }

    /**
     * Scrape a single article page and create the database record.
     */
    private function scrapeArticle(string $url, ?string $lastmod, string $sourceType): void
    {
        // Derive slug from URL
        $path = parse_url($url, PHP_URL_PATH);
        $slug = trim($path, '/');
        $slug = Str::slug($slug);

        // Skip if already imported (by source_url)
        if (Article::where('source_url', $url)->exists()) {
            return;
        }

        $response = Http::withHeaders([
            'User-Agent' => 'PandoraBot/1.0 (article importer)',
        ])->timeout(30)->get($url);

        if (! $response->successful()) {
            $this->errors++;
            $this->newLine();
            $this->warn("  Skipping {$url}: HTTP {$response->status()}");
            return;
        }

        $html = $response->body();

        // Parse the page
        $title = $this->extractTitle($html);
        $content = $this->extractContent($html);
        $ogImage = $this->extractOgImage($html, $url);

        if (! $title) {
            $this->errors++;
            $this->newLine();
            $this->warn("  Skipping {$url}: could not extract title");
            return;
        }

        // Download and localize images in content
        $content = $this->downloadAndReplaceImages($content, $sourceType, $slug);

        // Download featured image
        $featuredImagePath = null;
        if ($ogImage) {
            $featuredImagePath = $this->downloadImage($ogImage, $sourceType, $slug, 'featured');
        }

        // Legal-compliance pass: sanitize forbidden claims + append disclaimer
        $risks = $this->sanitizer->riskReport($title . ' ' . strip_tags($content));
        $title = $this->sanitizer->sanitizeText($title);
        $content = $this->sanitizer->process($content, 'article');
        if (! empty($risks)) {
            $this->sanitized++;
            Log::info('[scrape:jerosse] Sanitized risky terms', [
                'url'   => $url,
                'terms' => $risks,
            ]);
        }

        // Generate excerpt from content text (after sanitize)
        $excerpt = $this->generateExcerpt($content);

        // Determine published_at
        $publishedAt = $lastmod ? date('Y-m-d H:i:s', strtotime($lastmod)) : now();

        // Detect promotion end date: "▲活動時間：2025/12/25(四)12:00 - 2026/1/12(一) 23:59"
        $promoEndsAt = $this->extractPromoEndDate($content);

        // Create article
        $article = Article::create([
            'title'          => $title,
            'slug'           => $this->uniqueSlug($slug),
            'content'        => $content,
            'excerpt'        => $excerpt,
            'featured_image' => $featuredImagePath,
            'source_url'     => $url,
            'source_type'    => $sourceType,
            'status'         => 'published',
            'published_at'   => $publishedAt,
            'promo_ends_at'  => $promoEndsAt,
        ]);

        // Attach category
        $categorySlug = $this->detectCategory($url, $html, $sourceType);
        if ($categorySlug) {
            $category = ArticleCategory::where('slug', $categorySlug)->first();
            if ($category) {
                $article->categories()->attach($category->id);
            }
        }

        $this->articlesImported++;
    }

    /**
     * Extract the page title from <h1> or <title>.
     */
    private function extractTitle(string $html): ?string
    {
        // Try <h1> first
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m)) {
            $title = strip_tags($m[1]);
            $title = html_entity_decode(trim($title), ENT_QUOTES, 'UTF-8');
            if ($title) {
                return $title;
            }
        }

        // Fall back to <title>
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
            // Strip site name suffix (e.g. " - 婕樂纖")
            $title = preg_replace('/\s*[-|–]\s*.{0,20}$/', '', $title);
            return $title ?: null;
        }

        return null;
    }

    /**
     * Extract the main article content HTML.
     */
    private function extractContent(string $html): string
    {
        // Elementor-specific selectors for jerosse.com.tw (highest priority)
        $elementorPatterns = [
            // Primary: article-content widget (theme-post-content)
            '/<div[^>]*class="[^"]*\barticle-content\b[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si',
            // elementor-widget-theme-post-content
            '/<div[^>]*class="[^"]*\belementor-widget-theme-post-content\b[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si',
            // data-widget_type="theme-post-content.default"
            '/<div[^>]*data-widget_type=["\']theme-post-content\.default["\'][^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si',
            // ae-post-content widget
            '/<div[^>]*class="[^"]*\belementor-widget-ae-post-content\b[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si',
            // data-widget_type="ae-post-content.default"
            '/<div[^>]*data-widget_type=["\']ae-post-content\.default["\'][^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si',
        ];

        // Try Elementor patterns first (use DOMDocument for more reliable extraction)
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // DOMXPath queries for Elementor widgets (ordered by specificity)
        $xpathQueries = [
            // Primary: article-content class
            "//*[contains(@class, 'article-content')]",
            // theme-post-content widget
            "//*[contains(@class, 'elementor-widget-theme-post-content')]",
            // data-widget_type for theme-post-content
            "//*[@data-widget_type='theme-post-content.default']",
            // ae-post-content widget
            "//*[contains(@class, 'elementor-widget-ae-post-content')]",
            // data-widget_type for ae-post-content
            "//*[@data-widget_type='ae-post-content.default']",
        ];

        foreach ($xpathQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $node = $nodes->item(0);
                $content = $doc->saveHTML($node);
                if ($content && strlen(strip_tags($content)) > 50) {
                    $content = $this->cleanContent($content);
                    return trim($content);
                }
            }
        }

        // Fallback: WordPress common content selectors via regex
        $patterns = [
            // .entry-content
            '/<div[^>]*class="[^"]*\bentry-content\b[^"]*"[^>]*>(.*?)<\/div>\s*(?:<\/div>|<div[^>]*class="[^"]*\b(?:post-tags|share|related)\b)/si',
            // .entry-content (greedy fallback)
            '/<div[^>]*class="[^"]*\bentry-content\b[^"]*"[^>]*>(.*)/si',
            // article .content
            '/<article[^>]*>(.*?)<\/article>/si',
            // .post-content
            '/<div[^>]*class="[^"]*\bpost-content\b[^"]*"[^>]*>(.*?)<\/div>/si',
            // .content-area
            '/<div[^>]*class="[^"]*\bcontent-area\b[^"]*"[^>]*>(.*?)<\/div>/si',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $content = $m[1];
                $content = $this->cleanContent($content);
                return trim($content);
            }
        }

        // Last resort: try to get everything between <main> tags
        if (preg_match('/<main[^>]*>(.*?)<\/main>/si', $html, $m)) {
            return trim($this->cleanContent($m[1]));
        }

        return '';
    }

    /**
     * Clean extracted content: remove scripts, styles, jerosse links, empty tags, Elementor wrappers.
     */
    private function cleanContent(string $content): string
    {
        // Remove <script> tags
        $content = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $content);
        // Remove <style> tags
        $content = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $content);
        // Remove <link> tags (external CSS references)
        $content = preg_replace('/<link[^>]*>/si', '', $content);
        // Remove HTML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        // Remove elements/links containing "閱讀全文" or "繼續閱讀"
        $content = preg_replace('/<a[^>]*>[^<]*(?:閱讀全文|繼續閱讀)[^<]*<\/a>/su', '', $content);
        // Also remove standalone text blocks with these phrases
        $content = preg_replace('/<[^>]+>[^<]*(?:閱讀全文|繼續閱讀)[^<]*<\/[^>]+>/su', '', $content);

        // Remove <a> tags that link to jerosse.com.tw (keep the link text)
        $content = preg_replace('/<a[^>]+href=["\'][^"\']*jerosse\.com\.tw[^"\']*["\'][^>]*>(.*?)<\/a>/si', '$1', $content);

        // Remove Elementor-specific wrapper divs (keep inner content)
        // Remove elementor-widget-wrap, elementor-element, elementor-widget-container divs
        $content = preg_replace('/<div[^>]*class="[^"]*\belementor-widget-wrap\b[^"]*"[^>]*>/si', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*\belementor-widget-container\b[^"]*"[^>]*>/si', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*\belementor-element\b[^"]*"[^>]*>/si', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*\belementor-column\b[^"]*"[^>]*>/si', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*\belementor-row\b[^"]*"[^>]*>/si', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*\belementor-section\b[^"]*"[^>]*>/si', '', $content);
        $content = preg_replace('/<div[^>]*class="[^"]*\belementor-container\b[^"]*"[^>]*>/si', '', $content);
        // Remove data-element_type wrapper divs
        $content = preg_replace('/<div[^>]*data-element_type="[^"]*"[^>]*>/si', '', $content);
        // Remove data-widget_type wrapper divs
        $content = preg_replace('/<div[^>]*data-widget_type="[^"]*"[^>]*>/si', '', $content);

        // Clean up orphaned closing </div> tags by balancing
        // Count remaining opening vs closing div tags and trim excess closing tags
        $openDivs = preg_match_all('/<div[\s>]/i', $content);
        $closeDivs = preg_match_all('/<\/div>/i', $content);
        $excess = $closeDivs - $openDivs;
        if ($excess > 0) {
            // Remove excess closing </div> from the end
            for ($i = 0; $i < $excess; $i++) {
                $content = preg_replace('/<\/div>\s*$/i', '', $content);
            }
            // Also remove any remaining orphaned </div> tags
            // Simple approach: repeatedly remove </div> that don't have a matching opening
            $content = preg_replace('/^\s*<\/div>/im', '', $content);
        }

        // Remove empty <div> and <p> tags (including those with only whitespace)
        $content = preg_replace('/<div[^>]*>\s*<\/div>/si', '', $content);
        $content = preg_replace('/<p[^>]*>\s*<\/p>/si', '', $content);
        // Run again to catch nested empties
        $content = preg_replace('/<div[^>]*>\s*<\/div>/si', '', $content);
        $content = preg_replace('/<p[^>]*>\s*<\/p>/si', '', $content);

        // Remove excessive whitespace but keep structure
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        return $content;
    }

    /**
     * Extract the og:image URL from meta tags.
     */
    private function extractOgImage(string $html, string $baseUrl): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return $this->resolveUrl($m[1], $baseUrl);
        }

        // Also try reverse attribute order
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            return $this->resolveUrl($m[1], $baseUrl);
        }

        return null;
    }

    /**
     * Find all images in content HTML, download them, and replace src attributes.
     */
    private function downloadAndReplaceImages(string $content, string $sourceType, string $slug): string
    {
        if (! $content) {
            return $content;
        }

        $imageIndex = 0;

        $content = preg_replace_callback(
            '/<img[^>]+src=["\']([^"\']+)["\']/i',
            function ($matches) use ($sourceType, $slug, &$imageIndex) {
                $originalSrc = $matches[1];
                $imageIndex++;

                $localPath = $this->downloadImage(
                    $this->resolveUrl($originalSrc, 'https://jerosse.com.tw'),
                    $sourceType,
                    $slug,
                    (string) $imageIndex
                );

                if ($localPath) {
                    return str_replace($originalSrc, '/storage/' . $localPath, $matches[0]);
                }

                return $matches[0];
            },
            $content
        );

        return $content;
    }

    /**
     * Download an image and save it to storage.
     *
     * @return string|null The relative storage path on success, null on failure.
     */
    private function downloadImage(string $url, string $sourceType, string $slug, string $suffix): ?string
    {
        try {
            // Skip data URIs or empty
            if (! $url || str_starts_with($url, 'data:')) {
                return null;
            }

            $response = Http::withHeaders([
                'User-Agent' => 'PandoraBot/1.0 (article importer)',
            ])->timeout(30)->get($url);

            if (! $response->successful()) {
                $this->newLine();
                $this->warn("  Failed to download image: {$url} (HTTP {$response->status()})");
                return null;
            }

            $body = $response->body();
            if (strlen($body) < 100) {
                return null; // Skip tiny/empty responses
            }

            // Determine extension from URL or content type
            $ext = $this->getImageExtension($url, $response->header('Content-Type'));

            $filename = Str::limit($slug, 80, '') . "-{$suffix}.{$ext}";
            $storagePath = "articles/{$sourceType}/{$filename}";

            Storage::disk('public')->put($storagePath, $body);

            $this->imagesDownloaded++;

            return $storagePath;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->warn("  Failed to download image: {$url} ({$e->getMessage()})");
            return null;
        }
    }

    /**
     * Determine image file extension from URL path or content type header.
     */
    private function getImageExtension(string $url, ?string $contentType): string
    {
        // Try from URL path
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'])) {
                return $ext;
            }
        }

        // Try from content type
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
        ];

        if ($contentType && isset($map[$contentType])) {
            return $map[$contentType];
        }

        return 'jpg'; // Default
    }

    /**
     * Resolve a possibly relative URL against a base URL.
     */
    private function resolveUrl(string $url, string $base): string
    {
        // Already absolute
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Protocol-relative
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $parsed = parse_url($base);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? 'jerosse.com.tw';

        // Absolute path
        if (str_starts_with($url, '/')) {
            return "{$scheme}://{$host}{$url}";
        }

        // Relative path
        $basePath = dirname($parsed['path'] ?? '/');
        return "{$scheme}://{$host}{$basePath}/{$url}";
    }

    /**
     * Generate a plain-text excerpt from HTML content.
     */
    private function generateExcerpt(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return Str::limit($text, 200);
    }

    /**
     * Generate a unique slug, appending a suffix if needed.
     */
    private function uniqueSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;

        while (Article::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Detect promotion end date from body text.
     *
     * Prefers 正式活動時間 (Taiwan main-market period) when present.
     * Falls back to the EARLIEST end date across all matched patterns so
     * Taiwan users see promos expire on the correct (earlier) date.
     *
     * Handles:
     *   「▲正式活動時間：2025/12/25(四)12:00 - 2026/1/12(一) 23:59」
     *   「▲海外活動時間：2025/12/25 12:00 - 2026/1/12 23:59」
     *   「活動時間：2025/12/25 ~ 2026/1/12」
     */
    private function extractPromoEndDate(string $html): ?string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $normalized = preg_replace('/\([一二三四五六日]\)/u', '', $text) ?? $text;

        // Pattern to extract end date (allow optional prefix like "正式" / "海外")
        $extractEnd = static function (string $input): ?string {
            $pattern = '/活動時間\s*[:：]?\s*\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\s*\d{0,2}:?\d{0,2}\s*[-~～–]\s*(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?/u';
            if (! preg_match_all($pattern, $input, $m, PREG_SET_ORDER)) return null;
            $earliest = null;
            foreach ($m as $match) {
                $y = (int) $match[1]; $mo = (int) $match[2]; $d = (int) $match[3];
                $h = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : 23;
                $mi = isset($match[5]) && $match[5] !== '' ? (int) $match[5] : 59;
                $ts = sprintf('%04d-%02d-%02d %02d:%02d:00', $y, $mo, $d, $h, $mi);
                if ($earliest === null || strcmp($ts, $earliest) < 0) {
                    $earliest = $ts;
                }
            }
            return $earliest;
        };

        // Prefer the 正式活動時間 block specifically (Taiwan main market)
        if (preg_match('/正式活動時間[^。]*?\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}[^。]{0,60}/u', $normalized, $m)) {
            $official = $extractEnd($m[0]);
            if ($official) return $official;
        }

        // Fallback: take the earliest end across all matches
        return $extractEnd($normalized);
    }

    /**
     * Detect the article category from the URL or page content.
     */
    private function detectCategory(string $url, string $html, string $sourceType): ?string
    {
        $categories = self::CATEGORY_MAP[$sourceType] ?? [];
        $urlLower = strtolower($url);
        $textLower = strtolower(strip_tags($html));

        foreach ($categories as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                // Check URL first (more reliable)
                if (str_contains($urlLower, strtolower($keyword))) {
                    return $slug;
                }
            }
        }

        // Check page content for Chinese keywords
        foreach ($categories as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($textLower, strtolower($keyword))) {
                    return $slug;
                }
            }
        }

        // Default: first category for this type
        $firstSlug = array_key_first($categories);
        return $firstSlug ?: null;
    }
}
