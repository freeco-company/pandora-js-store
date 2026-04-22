<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use Illuminate\Http\JsonResponse;

/**
 * Public API for individual bundles at /api/bundles/{slug}.
 * Only bundles whose parent campaign is currently running are accessible
 * — ended / upcoming bundles 404, matching /campaigns/{slug} behavior.
 */
class BundleController extends Controller
{
    /**
     * Returns the bundle regardless of whether the parent campaign is running,
     * so the frontend can show a friendly 「尚未開始 / 已結束」 state.
     * Only bundles under an unpublished (is_active=false) campaign 404.
     */
    public function show(string $slug): JsonResponse
    {
        $bundle = Bundle::where('slug', $slug)
            ->with(['campaign', 'buyItems', 'giftItems'])
            ->firstOrFail();

        if (! $bundle->campaign || ! $bundle->campaign->is_active) {
            abort(404);
        }

        return response()->json($this->serialize($bundle));
    }

    public static function serialize(Bundle $bundle): array
    {
        $mapItem = fn ($p) => [
            'product' => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'image' => $p->image,
                'price' => (float) $p->price,
                'combo_price' => $p->combo_price !== null ? (float) $p->combo_price : null,
                'vip_price' => $p->vip_price !== null ? (float) $p->vip_price : null,
                'short_description' => $p->short_description,
            ],
            'quantity' => (int) $p->pivot->quantity,
        ];

        $campaign = $bundle->campaign;

        return [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'slug' => $bundle->slug,
            'description' => $bundle->description,
            'image' => $bundle->image,
            'bundle_price' => $bundle->bundlePrice(),
            'bundle_value_price' => $bundle->valuePrice(),
            'custom_gifts' => array_values(array_filter(
                (array) ($bundle->custom_gifts ?? []),
                fn ($g) => filled($g['name'] ?? null),
            )),
            'buy_items' => $bundle->buyItems->map($mapItem)->values(),
            'gift_items' => $bundle->giftItems->map($mapItem)->values(),
            'campaign' => $campaign ? [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'description' => $campaign->description,
                'start_at' => $campaign->start_at->toIso8601String(),
                'end_at' => $campaign->end_at->toIso8601String(),
                'is_running' => $campaign->isRunning(),
                'has_ended' => $campaign->hasEnded(),
            ] : null,
        ];
    }
}
