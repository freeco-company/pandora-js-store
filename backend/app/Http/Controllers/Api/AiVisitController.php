<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Upsert-increment aggregated AI visit counters. Called fire-and-forget
 * by the Next.js proxy when it detects an AI bot UA or AI-origin referer.
 * Bounded storage — ~20 rows/day max regardless of crawl volume.
 */
class AiVisitController extends Controller
{
    private const BOT_TYPES = [
        'claude', 'gpt', 'perplexity', 'google_ai',
        'apple', 'bytedance', 'meta', 'amazon',
        'common_crawl', 'cohere', 'other',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_type' => 'required|string|max:32',
            'source' => 'required|in:bot,user',
            'path' => 'nullable|string|max:255',
        ]);

        $botType = in_array($data['bot_type'], self::BOT_TYPES, true) ? $data['bot_type'] : 'other';
        $now = now();

        // Atomic increment on (date, bot_type, source) — MariaDB's ON DUPLICATE
        // KEY UPDATE keeps hits monotonic even under concurrent crawls.
        DB::statement(
            'INSERT INTO ai_visits_daily (date, bot_type, source, hits, last_path, last_seen_at, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               hits = hits + 1,
               last_path = VALUES(last_path),
               last_seen_at = VALUES(last_seen_at),
               updated_at = VALUES(updated_at)',
            [
                $now->toDateString(),
                $botType,
                $data['source'],
                $data['path'] ?? null,
                $now,
                $now,
                $now,
            ]
        );

        // Per-path bucket — answers "which products/articles is AI citing most?"
        // Truncate to 255 chars (column width) and normalize empty/missing.
        $path = $data['path'] ?? null;
        if ($path !== null && $path !== '') {
            $path = mb_substr($path, 0, 255);
            DB::statement(
                'INSERT INTO ai_visits_by_path (date, path, bot_type, hits, last_seen_at, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   hits = hits + 1,
                   last_seen_at = VALUES(last_seen_at),
                   updated_at = VALUES(updated_at)',
                [
                    $now->toDateString(),
                    $path,
                    $botType,
                    $now,
                    $now,
                    $now,
                ]
            );
        }

        return response()->json(['ok' => true]);
    }
}
