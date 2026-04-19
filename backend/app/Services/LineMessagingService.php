<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for LINE Messaging API push messages.
 *
 * Why "push"? Replaces the deprecated LINE Notify (sunset 2025-03-31).
 * Push requires:
 *   1. The customer is a friend of the brand's LINE OA
 *   2. Their LINE userId is stored on the customer (captured via LINE Login)
 *   3. The Login + Messaging channels live under the same LINE Provider
 *      so the userId from one is valid in the other.
 *
 * If the access token isn't configured the client silently no-ops so the
 * caller (e.g. the abandoned-cart command) can fire-and-forget without
 * branching on env. Failures are logged but never raise.
 */
class LineMessagingService
{
    private const PUSH_URL = 'https://api.line.me/v2/bot/message/push';

    public function __construct(private ?string $accessToken = null)
    {
        $this->accessToken ??= config('services.line.messaging_access_token');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Push a plain-text message to a single LINE userId.
     * Returns true on 2xx, false otherwise (incl. unconfigured).
     *
     * @param  array<int,array<string,string>>|null  $quickReplies  Optional buttons:
     *         [{label: '前往結帳', uri: 'https://...'}]
     */
    public function pushText(string $userId, string $text, ?array $quickReplies = null): bool
    {
        if (!$this->isConfigured() || trim($userId) === '') return false;

        $message = ['type' => 'text', 'text' => mb_substr($text, 0, 5000)];

        if ($quickReplies) {
            $message['quickReply'] = [
                'items' => array_map(fn ($q) => [
                    'type' => 'action',
                    'action' => ['type' => 'uri', 'label' => mb_substr($q['label'], 0, 20), 'uri' => $q['uri']],
                ], $quickReplies),
            ];
        }

        try {
            $res = Http::withToken($this->accessToken)
                ->timeout(8)
                ->post(self::PUSH_URL, [
                    'to' => $userId,
                    'messages' => [$message],
                ]);

            if (!$res->successful()) {
                Log::warning('[line.push] failed', [
                    'status' => $res->status(),
                    'body' => $res->body(),
                    'user_id_prefix' => substr($userId, 0, 6),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('[line.push] exception', ['msg' => $e->getMessage()]);
            return false;
        }
    }
}
