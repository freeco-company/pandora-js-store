<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShortLink;
use Illuminate\Http\JsonResponse;

class ShortLinkController extends Controller
{
    /**
     * GET /api/short-links/{code}/resolve
     *
     * Bumps click_count and returns the long URL (with utm_*) so the
     * Next.js /p/[code] route handler can issue the actual 302 on the
     * customer-facing domain. Backend stays the source of truth for
     * counts; the frontend is a thin redirect proxy.
     *
     * Misses (unknown / expired) return { url: null } and the frontend
     * falls back to the storefront homepage so a stale post never 404s.
     */
    public function resolve(string $code): JsonResponse
    {
        $link = ShortLink::where('code', $code)->first();
        if (! $link || $link->isExpired()) {
            return response()->json(['url' => null]);
        }

        // Single UPDATE — race-safe vs concurrent clicks.
        $link->increment('click_count');

        return response()->json(['url' => $link->target_url]);
    }
}
