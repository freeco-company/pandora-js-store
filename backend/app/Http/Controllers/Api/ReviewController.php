<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReviewController extends Controller
{
    /**
     * Get reviews for a product (public).
     */
    public function index(string $slug): JsonResponse
    {
        $product = Product::visible()
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug)->orWhere('slug_legacy', $slug);
            })
            ->firstOrFail();

        $cacheKey = "reviews:product:{$product->id}";
        $data = Cache::remember($cacheKey, 600, function () use ($product) {
            $reviews = Review::where('product_id', $product->id)
                ->where('is_visible', true)
                ->orderByDesc('created_at')
                ->get(['id', 'rating', 'content', 'reviewer_name', 'is_verified_purchase', 'created_at']);

            $count = $reviews->count();
            $avg = $count > 0 ? round($reviews->avg('rating'), 1) : 0;

            // Star distribution
            $distribution = [];
            for ($i = 5; $i >= 1; $i--) {
                $distribution[$i] = $reviews->where('rating', $i)->count();
            }

            return [
                'average_rating' => $avg,
                'total_count' => $count,
                'distribution' => $distribution,
                'reviews' => $reviews->map(fn ($r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'content' => $r->content,
                    'reviewer_name' => $r->reviewer_name,
                    'is_verified_purchase' => $r->is_verified_purchase,
                    'created_at' => $r->created_at->toISOString(),
                ])->values()->all(),
            ];
        });

        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * Aggregate reviews across all products (for /reviews landing page).
     */
    public function aggregate(): JsonResponse
    {
        $data = Cache::remember('reviews:aggregate', 600, function () {
            // Only include reviews for currently-visible products
            $reviews = Review::where('is_visible', true)
                ->whereHas('product', fn ($q) => $q->visible())
                ->with('product:id,name,slug,image')
                ->orderByDesc('created_at')
                ->get();

            $totalCount = $reviews->count();
            $avgRating = $totalCount > 0 ? round($reviews->avg('rating'), 1) : 0;

            // Per-product summary
            $byProduct = $reviews->groupBy('product_id')->map(function ($group) {
                $product = $group->first()->product;
                return [
                    'product_id' => $product?->id,
                    'product_name' => $product?->name,
                    'product_slug' => $product?->slug,
                    'product_image' => $product?->image,
                    'count' => $group->count(),
                    'average_rating' => round($group->avg('rating'), 1),
                ];
            })->sortByDesc('count')->values()->all();

            // Recent reviews with product info (latest 30)
            $recent = $reviews->take(30)->map(fn ($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'content' => $r->content,
                'reviewer_name' => $r->reviewer_name,
                'is_verified_purchase' => $r->is_verified_purchase,
                'product_name' => $r->product?->name,
                'product_slug' => $r->product?->slug,
                'created_at' => $r->created_at->toISOString(),
            ])->values()->all();

            return [
                'total_count' => $totalCount,
                'average_rating' => $avgRating,
                'products' => $byProduct,
                'recent_reviews' => $recent,
            ];
        });

        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * Get products the customer has purchased (completed orders) but not yet reviewed.
     */
    public function reviewable(Request $request): JsonResponse
    {
        $customer = $request->user();

        $completedOrders = Order::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->with('items.product')
            ->get();

        $reviewable = [];
        foreach ($completedOrders as $order) {
            foreach ($order->items as $item) {
                if (! $item->product) continue;

                // Check if already reviewed for this order
                $alreadyReviewed = Review::where('customer_id', $customer->id)
                    ->where('product_id', $item->product_id)
                    ->where('order_id', $order->id)
                    ->exists();

                if (! $alreadyReviewed) {
                    $reviewable[] = [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'product_slug' => $item->product->slug,
                        'product_image' => $item->product->image,
                        'completed_at' => $order->updated_at->toISOString(),
                    ];
                }
            }
        }

        return response()->json($reviewable);
    }

    /**
     * Submit a review (authenticated, must have completed order).
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:500',
        ]);

        // Verify the customer owns this completed order and it contains this product
        $order = Order::where('id', $validated['order_id'])
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('items', fn ($q) => $q->where('product_id', $validated['product_id']))
            ->firstOrFail();

        // Check not already reviewed
        $exists = Review::where('customer_id', $customer->id)
            ->where('product_id', $validated['product_id'])
            ->where('order_id', $order->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => '您已經評論過此商品'], 422);
        }

        // Mask customer name
        $maskedName = self::maskName($customer->name, $customer->email);

        $review = Review::create([
            'product_id' => $validated['product_id'],
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'content' => $validated['content'] ?: null,
            'reviewer_name' => $maskedName,
            'is_verified_purchase' => true,
            'is_seeded' => false,
        ]);

        // Bust cache
        Cache::forget("reviews:product:{$validated['product_id']}");

        // Evaluate review-related achievements (idempotent, AchievementService handles dedupe)
        // - first_review: 第一則評論
        // - review_3 / 5 / 10: 累積評論里程碑
        // - quality_review: 寫超過 30 字的詳細評論
        $reviewCount = \App\Models\Review::where('customer_id', $customer->id)
            ->where('is_seeded', false)
            ->count();

        $codes = [\App\Services\AchievementCatalog::FIRST_REVIEW];
        if ($reviewCount >= 3) $codes[] = \App\Services\AchievementCatalog::REVIEW_3;
        if ($reviewCount >= 5) $codes[] = \App\Services\AchievementCatalog::REVIEW_5;
        if ($reviewCount >= 10) $codes[] = \App\Services\AchievementCatalog::REVIEW_10;

        $contentLen = mb_strlen(trim((string) $review->content));
        if ($contentLen >= 30) $codes[] = \App\Services\AchievementCatalog::QUALITY_REVIEW;

        $awarded = app(\App\Services\AchievementService::class)->awardMany($customer, $codes);

        return response()->json(array_merge(
            ['message' => '感謝您的評論！', 'review' => $review],
            ['_achievements' => $awarded],
        ), 201);
    }

    /**
     * Mask a customer name for public display.
     * 王大明 → 王*明, 陳小 → 陳*, abc@gmail.com → a**@gmail.com
     */
    public static function maskName(string $name, ?string $email = null): string
    {
        $name = trim($name);

        // If name looks like an email, mask the email
        if (str_contains($name, '@') || empty($name)) {
            $e = $email ?: $name;
            if (str_contains($e, '@')) {
                [$local, $domain] = explode('@', $e);
                if (mb_strlen($local) <= 2) {
                    return $local[0] . '*@' . $domain;
                }
                return mb_substr($local, 0, 1) . str_repeat('*', min(mb_strlen($local) - 2, 3)) . mb_substr($local, -1) . '@' . $domain;
            }
            return '匿名會員';
        }

        $len = mb_strlen($name);
        if ($len <= 1) return $name . '*';
        if ($len === 2) return mb_substr($name, 0, 1) . '*';
        // 3+ chars: keep first and last, replace middle with *
        return mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1);
    }
}
