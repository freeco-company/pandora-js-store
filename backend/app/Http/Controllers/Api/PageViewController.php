<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Minimal first-party page-view tracking for the dashboard 今日瀏覽人數
 * widget. Intentionally not a full analytics stack — one row per tracked
 * hit, unique visitors derived from COUNT(DISTINCT session_id).
 */
class PageViewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => 'required|string|max:255',
            'session_id' => 'required|uuid',
            'referer' => 'nullable|string|max:500',
        ]);

        DB::table('page_views')->insert([
            'path' => $data['path'],
            'session_id' => $data['session_id'],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'referer' => $data['referer'] ?? null,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
