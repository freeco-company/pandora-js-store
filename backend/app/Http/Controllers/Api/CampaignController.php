<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    /** Currently running bundle promotions only. */
    public function index(): JsonResponse
    {
        $campaigns = Campaign::active()
            ->with(['buyItems', 'giftItems'])
            ->orderBy('start_at')
            ->get()
            ->map(fn ($c) => $this->serializeBundle($c));

        return response()->json($campaigns);
    }

    /** Single bundle by slug — 404 outside the running window. */
    public function show(string $slug): JsonResponse
    {
        $campaign = Campaign::active()
            ->where('slug', $slug)
            ->with(['buyItems', 'giftItems'])
            ->firstOrFail();

        return response()->json($this->serializeBundle($campaign));
    }

    /**
     * Bundle-shaped response used by both /campaigns/[slug] (detail) and
     * the cart calculator. Buy items feed into the price; gift items are
     * displayed but free.
     */
    private function serializeBundle(Campaign $c): array
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

        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'image' => $c->image,
            'banner_image' => $c->banner_image,
            'start_at' => $c->start_at->toIso8601String(),
            'end_at' => $c->end_at->toIso8601String(),
            'is_running' => $c->isRunning(),
            'bundle_price' => $c->bundlePrice(),
            'bundle_original_price' => $c->bundleOriginalPrice(),
            'buy_items' => $c->buyItems->map($mapItem)->values(),
            'gift_items' => $c->giftItems->map($mapItem)->values(),
        ];
    }
}
