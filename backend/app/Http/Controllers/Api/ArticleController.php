<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    private const CACHE_TTL = 600; // 10 min
    private const VERSION_KEY = 'articles:cache_version';

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
        $cacheKey = "articles:index:v{$this->version()}:" . md5(json_encode([
            'type'     => $request->get('type'),
            'category' => $request->get('category'),
            'per_page' => $request->get('per_page', 12),
            'page'     => $request->get('page', 1),
        ]));

        // Cache the serialized array (not the Paginator object — file cache can't round-trip it)
        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = Article::where('status', 'published')
                ->with(['categories', 'seoMeta'])
                // Hide expired promotions (news articles with past activity end date)
                ->where(function ($q) {
                    $q->whereNull('promo_ends_at')->orWhere('promo_ends_at', '>', now());
                });

            if ($request->has('type')) {
                $types = array_filter(array_map('trim', explode(',', (string) $request->type)));
                if (count($types) === 1) {
                    $query->where('source_type', $types[0]);
                } elseif (count($types) > 1) {
                    $query->whereIn('source_type', $types);
                }
            }

            if ($request->has('category')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }

            $articles = $query->orderByDesc('published_at')
                ->paginate($request->get('per_page', 12));

            $articles->getCollection()->transform(fn ($a) => $this->normalizeArticle($a));
            return $articles->toArray();
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=600, stale-while-revalidate=300');
    }

    public function show(string $slug): JsonResponse
    {
        $payload = Cache::remember("articles:show:v{$this->version()}:{$slug}", self::CACHE_TTL, function () use ($slug) {
            $article = Article::where('slug', $slug)
                ->where('status', 'published')
                // Expired promo articles 404 on detail page too, not just list
                ->where(function ($q) {
                    $q->whereNull('promo_ends_at')->orWhere('promo_ends_at', '>', now());
                })
                ->with(['categories', 'seoMeta'])
                ->firstOrFail();
            return $this->normalizeArticle($article)->toArray();
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=600, stale-while-revalidate=300');
    }

    public function categories(Request $request): JsonResponse
    {
        $cacheKey = "articles:categories:v{$this->version()}:" . ($request->get('type') ?? 'all');
        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
            $query = ArticleCategory::withCount('articles');
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            return $query->get()->toArray();
        });

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=600, stale-while-revalidate=300');
    }

    private function normalizeArticle(Article $article): Article
    {
        // Fix content: unescape double-escaped HTML
        if ($article->content) {
            $article->content = stripslashes($article->content);
        }

        // Remove WP lazy-load placeholder images (data:image/svg+xml src with data-lazy-src)
        if ($article->content) {
            // Replace img tags that have data-lazy-src: use data-lazy-src as the real src
            $article->content = preg_replace_callback(
                '/<img([^>]*)src="data:image\/svg\+xml[^"]*"([^>]*)data-lazy-src="([^"]*)"([^>]*)>/i',
                fn ($m) => '<img' . $m[1] . 'src="' . $m[3] . '"' . $m[2] . $m[4] . '>',
                $article->content
            );
        }

        // Remove <noscript> blocks (WP lazy-load duplicates every image inside noscript)
        if ($article->content) {
            $article->content = preg_replace('/<noscript>.*?<\/noscript>/s', '', $article->content);
        }

        // Strip ALL WP noise data-* attributes (lazy-load, path-to-node, etc)
        if ($article->content) {
            $article->content = preg_replace(
                '/\s+data-[a-z0-9-]+=(?:"[^"]*"|\'[^\']*\'|[^"\'\s>]+)/i',
                '',
                $article->content
            );
            // Strip malformed inline styles (from double-escaped WP content)
            $article->content = preg_replace('/\s+style=[\'"]\s*[\'"][^>]*?:[\'"][^>]*?[\'"]/', '', $article->content);
        }

        return $article;
    }
}
