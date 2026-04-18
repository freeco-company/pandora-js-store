<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    /** Currently running campaigns with their bundles. */
    public function index(): JsonResponse
    {
        $campaigns = Campaign::active()
            ->with(['bundles.buyItems', 'bundles.giftItems'])
            ->orderBy('start_at')
            ->get()
            ->map(fn ($c) => $this->serialize($c));

        return response()->json($campaigns);
    }

    /** Single campaign (with bundles) by slug — 404 when not running. */
    public function show(string $slug): JsonResponse
    {
        $campaign = Campaign::active()
            ->where('slug', $slug)
            ->with(['bundles.buyItems', 'bundles.giftItems'])
            ->firstOrFail();

        return response()->json($this->serialize($campaign));
    }

    private function serialize(Campaign $c): array
    {
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
            'bundles' => $c->bundles->map(fn ($b) => $this->serializeBundle($b))->values(),
        ];
    }

    private function serializeBundle($b): array
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
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'description' => $b->description,
            'image' => $b->image,
            'bundle_price' => $b->bundlePrice(),
            'bundle_original_price' => $b->bundleOriginalPrice(),
            'buy_items' => $b->buyItems->map($mapItem)->values(),
            'gift_items' => $b->giftItems->map($mapItem)->values(),
        ];
    }
}
