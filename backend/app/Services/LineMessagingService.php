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
    private const REPLY_URL = 'https://api.line.me/v2/bot/message/reply';

    public function __construct(
        private ?string $accessToken = null,
        private ?string $channelSecret = null,
    ) {
        $this->accessToken ??= config('services.line.messaging_access_token');
        // Webhook signature validation uses the Messaging channel secret
        // (NOT the Login channel secret used by Socialite).
        $this->channelSecret ??= config('services.line.messaging_channel_secret');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Verify LINE webhook signature.
     * X-Line-Signature: base64(HMAC-SHA256(channelSecret, body))
     */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        if (empty($this->channelSecret) || empty($signature)) return false;
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $this->channelSecret, true));
        return hash_equals($expected, $signature);
    }

    /**
     * Push a Flex Message ("確認出貨" 按鈕). Returns true on 2xx.
     *
     * @param  array<string,mixed>  $flexContents
     */
    public function pushFlex(string $userId, string $altText, array $flexContents): bool
    {
        if (!$this->isConfigured() || trim($userId) === '') return false;

        try {
            $res = Http::withToken($this->accessToken)
                ->timeout(8)
                ->post(self::PUSH_URL, [
                    'to' => $userId,
                    'messages' => [[
                        'type' => 'flex',
                        'altText' => mb_substr($altText, 0, 400),
                        'contents' => $flexContents,
                    ]],
                ]);

            if (!$res->successful()) {
                Log::warning('[line.flex] push failed', ['status' => $res->status(), 'body' => $res->body()]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('[line.flex] push exception', ['msg' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Reply to a webhook event using its replyToken (only valid ~1 minute).
     */
    public function replyText(string $replyToken, string $text): bool
    {
        if (!$this->isConfigured() || trim($replyToken) === '') return false;

        try {
            $res = Http::withToken($this->accessToken)
                ->timeout(8)
                ->post(self::REPLY_URL, [
                    'replyToken' => $replyToken,
                    'messages' => [['type' => 'text', 'text' => mb_substr($text, 0, 5000)]],
                ]);
            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('[line.reply] exception', ['msg' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Build the COD-confirmation Flex Message bubble. The "確認出貨" button
     * fires a postback with `data` carrying the order_number + token; the
     * webhook controller verifies the token before flipping status.
     *
     * @return array<string,mixed>
     */
    public static function codConfirmationFlex(string $orderNumber, string $token, int $total): array
    {
        $totalStr = 'NT$' . number_format($total);
        $postbackData = http_build_query([
            'action' => 'confirm_cod',
            'order' => $orderNumber,
            'token' => $token,
        ]);

        return [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'backgroundColor' => '#9F6B3E',
                'paddingAll' => '16px',
                'contents' => [
                    ['type' => 'text', 'text' => '婕樂纖仙女館', 'color' => '#FFFFFF', 'size' => 'sm', 'weight' => 'bold'],
                    ['type' => 'text', 'text' => '請確認您的貨到付款訂單', 'color' => '#FFFFFF', 'size' => 'lg', 'weight' => 'bold', 'margin' => 'sm'],
                ],
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'md',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => "您的訂單 {$orderNumber} 已成立！為確保您本人下單，請點擊下方按鈕確認超商「貨到付款」訂單，我們將立即為您安排出貨。",
                        'wrap' => true,
                        'size' => 'sm',
                        'color' => '#555555',
                    ],
                    [
                        'type' => 'separator',
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'baseline',
                        'contents' => [
                            ['type' => 'text', 'text' => '訂單金額', 'size' => 'sm', 'color' => '#888888', 'flex' => 2],
                            ['type' => 'text', 'text' => $totalStr, 'size' => 'md', 'color' => '#9F6B3E', 'weight' => 'bold', 'flex' => 3, 'align' => 'end'],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => '⏰ 48 小時內未確認，訂單將自動取消',
                        'size' => 'xs',
                        'color' => '#E0748C',
                        'wrap' => true,
                        'margin' => 'sm',
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'primary',
                        'color' => '#9F6B3E',
                        'action' => [
                            'type' => 'postback',
                            'label' => '✅ 確認出貨',
                            'data' => $postbackData,
                            'displayText' => '確認出貨',
                        ],
                    ],
                ],
            ],
        ];
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
