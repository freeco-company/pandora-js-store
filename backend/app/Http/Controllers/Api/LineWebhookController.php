<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LineMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives LINE Messaging API webhook events. Currently only handles the
 * "confirm_cod" postback fired by the Flex confirmation message — other
 * events (text replies, follow / unfollow) are ignored but acknowledged
 * with 200 so LINE doesn't retry forever.
 */
class LineWebhookController extends Controller
{
    public function __construct(
        private LineMessagingService $line,
        private OrderConfirmationController $confirmation,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Line-Signature');

        if (!$this->line->verifySignature($rawBody, $signature)) {
            Log::warning('[line.webhook] signature verify failed', [
                'sig_present' => !empty($signature),
                'body_len' => strlen($rawBody),
            ]);
            // Return 200 anyway — LINE retries 4xx aggressively, and a misconfigured
            // secret shouldn't DoS the bot. The log captures the failure.
            return response()->json(['ok' => false], 200);
        }

        $payload = json_decode($rawBody, true);
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];

        foreach ($events as $event) {
            try {
                $this->dispatchEvent($event);
            } catch (\Throwable $e) {
                Log::error('[line.webhook] event handler exception', [
                    'type' => $event['type'] ?? null,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function dispatchEvent(array $event): void
    {
        $type = $event['type'] ?? '';
        if ($type !== 'postback') return;

        $data = (string) ($event['postback']['data'] ?? '');
        parse_str($data, $parsed);

        $action = $parsed['action'] ?? '';
        if ($action !== 'confirm_cod') return;

        $orderNumber = (string) ($parsed['order'] ?? '');
        $token = (string) ($parsed['token'] ?? '');
        $lineUserId = (string) ($event['source']['userId'] ?? '');
        $replyToken = (string) ($event['replyToken'] ?? '');

        if ($orderNumber === '' || $token === '' || $lineUserId === '') {
            Log::warning('[line.webhook] confirm_cod missing fields');
            return;
        }

        $result = $this->confirmation->confirm($orderNumber, $token, $lineUserId);

        if ($replyToken !== '') {
            $this->line->replyText($replyToken, $result['message']);
        }
    }
}
