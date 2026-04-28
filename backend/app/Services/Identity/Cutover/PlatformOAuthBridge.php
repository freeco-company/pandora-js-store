<?php

namespace App\Services\Identity\Cutover;

use App\Services\Identity\PandoraCoreClient;
use Illuminate\Support\Facades\Log;

/**
 * 把 OAuth callback 拿到的 provider profile 推進 platform 取回 UUID v7。
 *
 * 用既有 internal mirror endpoint（POST /api/internal/mirror/customer-upsert，
 * 從 PR #9 開始就有）— 它接受母艦預期格式，回 uuid。新做一個 endpoint 重複
 * 沒意義。
 *
 * 失敗 → 不 throw，回 null + log warning，由呼叫端決定是 fallback 還是 fail。
 * （hard constraint：platform 任何一次故障不該讓客戶登不進來；fallback 由
 * IDENTITY_CUTOVER_FAIL_OPEN 決定）
 */
class PlatformOAuthBridge
{
    public function __construct(private PandoraCoreClient $client) {}

    /**
     * @param  'google'|'line'|'apple'  $provider
     * @return ?string platform UUID v7，失敗回 null
     */
    public function syncFromOAuth(
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $name,
        ?string $phone = null,
    ): ?string {
        $payload = [
            'fp_customer_id' => null,  // OAuth flow 不知道 母艦 customer id（cutover 後才會建）
            'email_canonical' => $email,
            'phone_canonical' => $phone,
            'display_name' => $name,
            'identities' => [
                [
                    'type' => $provider,  // 'google' / 'line' / 'apple'
                    'value' => $providerUserId,
                    'is_primary' => true,
                ],
            ],
        ];
        if ($email !== null && $email !== '') {
            $payload['identities'][] = [
                'type' => 'email',
                'value' => $email,
                'is_primary' => true,
            ];
        }

        $response = $this->client->customerUpsert($payload);
        if (! $response->success) {
            Log::warning('[CutoverBridge] platform upsert failed', [
                'provider' => $provider,
                'status' => $response->status,
                'error' => $response->error,
            ]);

            return null;
        }

        // platform MirrorController 實際回傳 'group_user_id'；'uuid'/'user.uuid'
        // 是早期設計遺留的相容別名。
        $uuid = $response->data['group_user_id']
            ?? $response->data['uuid']
            ?? $response->data['user']['uuid']
            ?? $response->data['data']['uuid']
            ?? null;

        if (! is_string($uuid) || $uuid === '') {
            Log::warning('[CutoverBridge] platform 200 but missing uuid', [
                'provider' => $provider,
                'response_keys' => array_keys($response->data),
            ]);

            return null;
        }

        return $uuid;
    }
}
