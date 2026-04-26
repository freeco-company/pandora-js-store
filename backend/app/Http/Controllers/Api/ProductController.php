<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /** Cache TTL in seconds — reduced by bumpVersion() on any Product/Category save. */
    private const CACHE_TTL = 600; // 10 min

    /** Key holding the current cache "version" — incrementing it instantly invalidates all derived keys. */
    private const VERSION_KEY = 'products:cache_version';

    /** Bump the version so all cached product responses rebuild on next request. */
    public static function bumpVersion(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    public function index(Request $request): JsonResponse
    {
        $cacheKey = "products:index:v{$this->version()}:" . md5(json_encode([
            'q'        => $request->get('q'),
            'category' => $request->get('category'),
        ]));

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
            // Visible = active AND (no campaigns OR at least one running campaign).
            // Products whose campaigns are all past/future are hidden.
            $query = Product::visible()
                ->with(['categories', 'seoMeta']);

            if ($request->filled('q')) {
                $keyword = $request->q;
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%{$keyword}%")
                      ->orWhere('short_description', 'LIKE', "%{$keyword}%");
                });
            }

            if ($request->has('category')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }

            $products = $query->orderBy('sort_order')->get();
            return $products->map(fn ($p) => $this->serializeProduct($p))->values()->toArray();
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=600, stale-while-revalidate=300');
    }

    public function show(string $slug): JsonResponse
    {
        $payload = Cache::remember("products:show:v{$this->version()}:{$slug}", self::CACHE_TTL, function () use ($slug) {
            // Try current slug first, then fall back to legacy slug.
            // If matched via legacy, frontend will permanentRedirect to canonical.
            // Campaign products are only accessible during their active period.
            $product = Product::visible()
                ->where(function ($q) use ($slug) {
                    $q->where('slug', $slug)->orWhere('slug_legacy', $slug);
                })
                ->with(['categories', 'seoMeta'])
                ->firstOrFail();

            $payload = $this->serializeProduct($product);

            // Top 6 articles that mention this product, ordered by mention density.
            $related = $product->articles()
                ->where('articles.status', 'published')
                ->orderByPivot('mention_count', 'desc')
                ->orderByDesc('articles.published_at')
                ->limit(6)
                ->get(['articles.id', 'articles.slug', 'articles.title', 'articles.excerpt', 'articles.featured_image', 'articles.source_type'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'slug' => $a->slug,
                    'title' => $a->title,
                    'excerpt' => $a->excerpt,
                    'featured_image' => $a->featured_image,
                    'source_type' => $a->source_type,
                ])
                ->all();
            $payload['related_articles'] = $related;

            return $payload;
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=600, stale-while-revalidate=300');
    }

    public function categories(): JsonResponse
    {
        $payload = Cache::remember("products:categories:v{$this->version()}", self::CACHE_TTL, function () {
            return ProductCategory::withCount('products')->orderBy('sort_order')->get()->toArray();
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=600, stale-while-revalidate=300');
    }

    /**
     * Serialize a product. Campaigns are bundle promotions surfaced via
     * /campaigns/{slug} — the product response stays clean and doesn't
     * carry per-campaign pricing.
     */
    private function serializeProduct(Product $product): array
    {
        $this->normalizeProduct($product);
        return $product->toArray();
    }

    /**
     * Normalize product data: fix gallery paths and unescape HTML descriptions.
     */
    private function normalizeProduct(Product $product): Product
    {
        // Fix gallery paths: ensure they start with /storage/
        if ($product->gallery) {
            $product->gallery = array_map(function ($path) {
                if (str_starts_with($path, 'http') || str_starts_with($path, '/storage/')) {
                    return $path;
                }
                return '/storage/' . ltrim($path, '/');
            }, $product->gallery);
        }

        // Unescape WP import double-escape + strip mangled attribute wrapping.
        // WP's WooCommerce export often leaves values like `class='"foo"' src="\"https://...\""`.
        // We unwrap these before running src-path normalization below.
        if ($product->description) {
            $d = stripslashes($product->description);
            // 1) Single-quote-wrapped double-quoted values: =' "x" ' → ="x"
            $d = preg_replace('/=\'"([^"\']*)"\'/', '="$1"', $d);
            $d = preg_replace('/=\'"([^"\']*)\'/', '="$1"', $d);
            // 2) Backslash-escaped quotes inside attribute values: src="\"x\"" → src="x"
            $d = preg_replace('/="\\\\"([^"]*)\\\\""/', '="$1"', $d);
            // 3) URL-encoded backslash-quote wrapping: src="%5C%22x%5C%22" → src="x"
            //    (happens because the escaped chars survived urlencode somewhere)
            $d = preg_replace('/="%5C%22([^"]*?)%5C%22"/', '="$1"', $d);
            $d = preg_replace('/="\/storage\/%5C%22([^"]*?)%5C%22"/', '="$1"', $d);
            $product->description = $d;
        }

        // Clean short_description: unescape, convert <br>/<li> to newlines, strip tags
        if ($product->short_description) {
            $desc = stripslashes($product->short_description);
            // Convert block-level tags to newlines before stripping
            $desc = preg_replace('/<br\s*\/?>/i', "\n", $desc);
            $desc = preg_replace('/<\/li>/i', "\n", $desc);
            $desc = preg_replace('/<\/p>/i', "\n", $desc);
            $desc = strip_tags($desc);
            // Normalize whitespace: collapse multiple blank lines
            $desc = preg_replace('/\n{3,}/', "\n\n", $desc);
            $product->short_description = trim($desc);
        }

        // Strip WP noise attributes — handles both double-quoted and single-quoted
        if ($product->description) {
            // Any data-* attribute (double-quoted or single-quoted or weird escaped-quote values)
            $product->description = preg_replace('/\s+data-[a-z0-9-]+=(?:"[^"]*"|\'[^\']*\'|[^"\'\s>]+)/i', '', $product->description);
            // Malformed style attributes like style='"color:'  (from WP double-escape artifacts)
            $product->description = preg_replace('/\s+style=[\'"]\s*[\'"][^>]*?:[\'"][^>]*?[\'"]/', '', $product->description);
            // Collapse repeated <br> sequences (>3 = likely corrupted paragraph separators)
            $product->description = preg_replace('/(<br\s*\/?>\s*){4,}/i', '<br><br>', $product->description);
        }

        // Convert WP lazy YouTube players to real iframes
        if ($product->description) {
            $product->description = preg_replace_callback(
                '/<div[^>]*class="[^"]*rll-youtube-player[^"]*"[^>]*data-src="([^"]*)"[^>]*data-alt="([^"]*)"[^>]*>.*?<\/div>\s*<\/div>/s',
                fn ($m) => '<div class="aspect-video my-4"><iframe src="' . $m[1] . '" title="' . htmlspecialchars($m[2]) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-full h-full rounded-lg"></iframe></div>',
                $product->description
            );
            // Fallback: any remaining rll-youtube-player without data-alt
            $product->description = preg_replace_callback(
                '/<div[^>]*class="[^"]*rll-youtube-player[^"]*"[^>]*data-src="([^"]*)"[^>]*>.*?<\/div>\s*<\/div>/s',
                fn ($m) => '<div class="aspect-video my-4"><iframe src="' . $m[1] . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-full h-full rounded-lg"></iframe></div>',
                $product->description
            );
        }

        // Fix images in description: ensure src paths have /storage/ prefix
        if ($product->description) {
            $product->description = preg_replace_callback(
                '/src=["\'](?!http)(?!\/storage\/)([^"\']+)["\']/i',
                fn ($m) => 'src="/storage/' . ltrim($m[1], '/') . '"',
                $product->description
            );
        }

        return $product;
    }
}
