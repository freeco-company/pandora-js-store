<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    /** Active + upcoming campaigns with their product IDs. */
    public function index(): JsonResponse
    {
        $campaigns = Campaign::where('is_active', true)
            ->where('end_at', '>', now())
            ->with('products:id,name,slug,image,price,combo_price,vip_price')
            ->orderBy('start_at')
            ->get()
            ->map(function ($c) {
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
                    'products' => $c->products->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'slug' => $p->slug,
                        'image' => $p->image,
                        'price' => $p->price,
                        'campaign_price' => $p->pivot->campaign_price,
                    ]),
                ];
            });

        return response()->json($campaigns);
    }

    /** Single campaign by slug. */
    public function show(string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)
            ->where('is_active', true)
            ->with('products:id,name,slug,image,price,combo_price,vip_price,short_description')
            ->firstOrFail();

        if ($campaign->hasEnded()) {
            return response()->json(['message' => '此活動已結束'], 410);
        }

        return response()->json([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            'image' => $campaign->image,
            'banner_image' => $campaign->banner_image,
            'start_at' => $campaign->start_at->toIso8601String(),
            'end_at' => $campaign->end_at->toIso8601String(),
            'is_running' => $campaign->isRunning(),
            'products' => $campaign->products,
        ]);
    }
}
