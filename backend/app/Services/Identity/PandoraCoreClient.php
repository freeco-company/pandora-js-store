<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Http;

/**
 * 呼叫 Pandora Core internal endpoints 的 HTTP client。
 *
 * 認證：dev/staging 用 X-Pandora-Internal-Secret header；prod 改 mTLS（ADR-006）。
 *
 * 為什麼不用 Sanctum / JWT：母艦 ↔ platform 是 server-to-server 信任關係，
 * 不是用戶代理，不需要 user-bound token；shared secret + mTLS 更輕。
 */
class PandoraCoreClient
{
    public function customerUpsert(array $payload): PandoraCoreResponse
    {
        $url = rtrim((string) config('services.pandora_core.base_url'), '/').'/api/internal/mirror/customer-upsert';
        $secret = (string) config('services.pandora_core.internal_secret');

        if ($secret === '' || config('services.pandora_core.base_url') === null) {
            return PandoraCoreResponse::misconfigured('PANDORA_CORE_BASE_URL or PANDORA_CORE_INTERNAL_SECRET missing');
        }

        $response = Http::withHeaders([
            'X-Pandora-Internal-Secret' => $secret,
            'Accept' => 'application/json',
        ])
            ->timeout(10)
            ->post($url, $payload);

        if ($response->successful()) {
            return PandoraCoreResponse::ok($response->json() ?? []);
        }

        return PandoraCoreResponse::failed(
            status: $response->status(),
            body: $response->body(),
        );
    }
}
