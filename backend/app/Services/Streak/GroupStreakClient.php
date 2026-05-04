<?php

namespace App\Services\Streak;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SPEC-cross-app-streak Phase 5B (母艦) — read集團 master streak from
 * pandora-core-conversion `/api/v1/internal/group-streak/{uuid}` 並 cache 30s.
 *
 * 設計原則：
 *   - 不在 hot path 上 block 主要 streak 流程：任何 HTTP / config / parse 失敗
 *     一律 swallow + log，回傳 null（caller 用「沒拿到就不疊 overlay」處理）。
 *   - 30s in-process cache 對齊 py-service 端 cache 視窗，避免每次 boot 都打網
 *     路；同一 user 連點 toast 不會放大成 N 次 round-trip。
 *   - 沒有 uuid 直接 skip（未綁 Pandora Core 的 customer）。
 *   - 認證走 X-Internal-Secret（py-service 該 endpoint 是 simple shared secret，
 *     不是 conversion/events 的 HMAC，見 pandora-core-conversion app/auth/internal.py）。
 *   - 重用 services.pandora_gamification base_url + shared_secret（同一個
 *     py-service deployment、同一把 secret），避免新增 env key。
 *
 * 文案規範：本 client 純資料層，不產 user-facing 文字。Frontend 用回傳值組
 * 「FP 團隊連續第 N 天」副標。
 */
class GroupStreakClient
{
    /**
     * 30 秒 cache window（對齊 py-service per-process TTL）。
     */
    public const CACHE_TTL_SECONDS = 30;

    private const CACHE_PREFIX = 'group-streak:';

    public function isConfigured(): bool
    {
        return (string) config('services.pandora_gamification.base_url', '') !== ''
            && (string) config('services.pandora_gamification.shared_secret', '') !== '';
    }

    /**
     * Fetch master streak for a Pandora Core uuid. Returns null on:
     *   - empty uuid（未綁定）
     *   - 未配置 base_url / shared_secret（local dev / staging）
     *   - 4xx / 5xx / timeout / parse 失敗（fail-soft）
     *
     * @return array{
     *     current_streak: int,
     *     longest_streak: int,
     *     last_login_date: ?string,
     *     last_seen_app: ?string,
     *     today_in_streak: bool,
     * }|null
     */
    public function fetch(string $pandoraUserUuid): ?array
    {
        if ($pandoraUserUuid === '') {
            return null;
        }
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX.$pandoraUserUuid;

        // Cache::remember would swallow our null sentinel and re-fetch; we
        // explicitly cache null-on-failure as a 30s "circuit breaker" so a
        // py-service blip doesn't flood with retries on every request.
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === '__null__' ? null : $cached;
        }

        $result = $this->fetchFresh($pandoraUserUuid);

        Cache::put(
            $cacheKey,
            $result ?? '__null__',
            self::CACHE_TTL_SECONDS,
        );

        return $result;
    }

    /**
     * @return array{
     *     current_streak: int,
     *     longest_streak: int,
     *     last_login_date: ?string,
     *     last_seen_app: ?string,
     *     today_in_streak: bool,
     * }|null
     */
    private function fetchFresh(string $pandoraUserUuid): ?array
    {
        $base = rtrim((string) config('services.pandora_gamification.base_url'), '/');
        $secret = (string) config('services.pandora_gamification.shared_secret');
        $timeout = (int) config('services.pandora_gamification.timeout', 5);

        $url = $base.'/api/v1/internal/group-streak/'.$pandoraUserUuid;

        try {
            $response = Http::withHeaders([
                'X-Internal-Secret' => $secret,
                'Accept' => 'application/json',
            ])
                ->timeout($timeout)
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('[GroupStreakClient] http exception (soft)', [
                'uuid' => $pandoraUserUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('[GroupStreakClient] non-2xx (soft)', [
                'uuid' => $pandoraUserUuid,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        return [
            'current_streak' => (int) ($data['current_streak'] ?? 0),
            'longest_streak' => (int) ($data['longest_streak'] ?? 0),
            'last_login_date' => isset($data['last_login_date']) && is_string($data['last_login_date'])
                ? $data['last_login_date']
                : null,
            'last_seen_app' => isset($data['last_seen_app']) && is_string($data['last_seen_app'])
                ? $data['last_seen_app']
                : null,
            'today_in_streak' => (bool) ($data['today_in_streak'] ?? false),
        ];
    }
}
