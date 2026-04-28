<?php

namespace App\Http\Middleware;

use App\Models\IdentityWebhookNonce;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 (ADR-007 Phase 2 / pandora-js-store#11)：驗 platform 來的 webhook。
 *
 * 三道檢查：
 *   1. timestamp ±5min（防 replay 老的 request）
 *   2. HMAC-SHA256 簽章 hash_equals 比對（防 timing attack + tamper）
 *   3. event_id 進 identity_webhook_nonces UNIQUE 表（防同一個 event 被處理兩次）
 *
 * 簽章基底必須與 platform `WebhookSigner` 完全一致：
 *   "{timestamp}.{event_id}.{raw_body}"
 *
 * 任何驗證失敗回 401。replay（同 event_id）回 200 noop（200 是設計選擇：
 * 告訴 publisher「這個 row 已處理可以標 sent，別再重送」）。
 */
class VerifyIdentityWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.pandora_core.webhook_secret');
        if ($secret === '') {
            Log::error('[IdentityWebhook] missing PANDORA_CORE_WEBHOOK_SECRET');

            return response()->json(['error' => 'webhook secret not configured'], 500);
        }

        $eventId = (string) $request->header('X-Pandora-Event-Id', '');
        $timestamp = (string) $request->header('X-Pandora-Timestamp', '');
        $signature = (string) $request->header('X-Pandora-Signature', '');

        if ($eventId === '' || $timestamp === '' || $signature === '') {
            return response()->json(['error' => 'missing signature headers'], 401);
        }

        // 1. timestamp 視窗
        $window = (int) config('services.pandora_core.webhook_window_seconds', 300);
        $diff = abs(time() - (int) $timestamp);
        if ($diff > $window) {
            return response()->json(['error' => 'timestamp out of window'], 401);
        }

        // 2. HMAC
        $body = $request->getContent();
        $expected = hash_hmac('sha256', "{$timestamp}.{$eventId}.{$body}", $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'signature mismatch'], 401);
        }

        // 3. event_id replay 防護：UNIQUE INSERT 即提交標記。
        //    重複 INSERT QueryException → 回 200 noop（已處理過）。
        try {
            IdentityWebhookNonce::create([
                'event_id' => $eventId,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return response()->json(['status' => 'duplicate', 'event_id' => $eventId], 200);
            }
            throw $e;
        }

        return $next($request);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // MySQL/MariaDB: 23000 + errno 1062；SQLite: 23000 + 'UNIQUE constraint'
        $code = (string) $e->getCode();
        $msg = $e->getMessage();

        return $code === '23000'
            || str_contains($msg, '1062')
            || str_contains($msg, 'UNIQUE constraint failed');
    }
}
