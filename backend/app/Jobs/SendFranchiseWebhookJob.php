<?php

namespace App\Jobs;

use App\Models\FranchiseOutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 送一筆 franchise_outbox_events 到 朵朵 (pandora-meal) 的
 * POST /api/internal/franchisee/webhook（HMAC SHA256 簽章）。
 *
 * Headers:
 *   X-Pandora-Event-Id   ← UUID v7，朵朵端用作 nonce dedupe
 *   X-Pandora-Timestamp  ← Unix seconds
 *   X-Pandora-Signature  ← hex(hmac_sha256(secret, "{ts}.{event_id}.{body}"))
 *
 * Backoff: 1m → 5m → 15m → 1h → 6h → 留 table（人工 triage）
 *
 * Idempotency：朵朵端用 event_id 作為 franchisee_webhook_nonces 的 nonce key，
 * 我們 retry 同一筆 event 時不會被誤算第二次（朵朵回 200 + duplicate flag）。
 */
class SendFranchiseWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BACKOFF_SECONDS = [60, 300, 900, 3600, 21600];

    public function __construct(public readonly int $outboxEventId) {}

    public function handle(): void
    {
        /** @var ?FranchiseOutboxEvent $event */
        $event = FranchiseOutboxEvent::find($this->outboxEventId);
        if ($event === null || $event->dispatched_at !== null) {
            return;
        }

        $url = (string) config('services.franchise_webhook.url');
        $secret = (string) config('services.franchise_webhook.secret');
        $timeout = (int) config('services.franchise_webhook.timeout', 10);
        $maxAttempts = (int) config('services.franchise_webhook.max_attempts', FranchiseOutboxEvent::MAX_ATTEMPTS);

        if ($url === '' || $secret === '') {
            // env 沒設好：留 pending，sweeper 之後再試。不前進 attempts（避免燒掉 5 次）。
            Log::warning('[FranchiseWebhook] Misconfigured (missing url/secret), skipping', [
                'event_id' => $event->event_id,
            ]);
            $event->next_retry_at = now()->addMinutes(5);
            $event->last_error = 'misconfigured: FRANCHISE_WEBHOOK_URL or MOTHERSHIP_FRANCHISE_WEBHOOK_SECRET missing';
            $event->save();

            return;
        }

        $body = json_encode($event->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signaturePayload = $timestamp.'.'.$event->event_id.'.'.$body;
        $signature = hash_hmac('sha256', $signaturePayload, $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Pandora-Event-Id' => $event->event_id,
                'X-Pandora-Timestamp' => $timestamp,
                'X-Pandora-Signature' => $signature,
            ])
                ->timeout($timeout)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            $this->markRetry($event, status: '0', error: 'http_exception: '.$e->getMessage(), maxAttempts: $maxAttempts);

            return;
        }

        if ($response->successful()) {
            // 朵朵端 200 = accepted（含 unmatched user / duplicate nonce — 都算成功）
            $event->dispatched_at = now();
            $event->last_status_code = (string) $response->status();
            $event->last_error = null;
            $event->save();

            return;
        }

        // 4xx 多半是 contract bug（簽章錯 / payload 壞），不該無腦 retry；
        // 但 401 在 secret rotation 時會臨時錯 → 仍 retry，看 sweeper 後續是否復原。
        // 簡化策略：所有非 2xx 一律 retry 直到 MAX_ATTEMPTS。
        $this->markRetry(
            $event,
            status: (string) $response->status(),
            error: substr((string) $response->body(), 0, 500),
            maxAttempts: $maxAttempts,
        );
    }

    private function markRetry(FranchiseOutboxEvent $event, string $status, string $error, int $maxAttempts): void
    {
        $event->attempts++;
        $event->last_status_code = $status;
        $event->last_error = $error;

        if ($event->attempts >= $maxAttempts) {
            $event->next_retry_at = null;  // 停止 retry，留 table 等人工 triage
            $event->save();
            Log::error('[FranchiseWebhook] Dead letter (exceeded max attempts)', [
                'event_id' => $event->event_id,
                'attempts' => $event->attempts,
                'last_status' => $status,
                'last_error' => $error,
            ]);

            return;
        }

        $idx = min($event->attempts - 1, count(self::BACKOFF_SECONDS) - 1);
        $event->next_retry_at = now()->addSeconds(self::BACKOFF_SECONDS[$idx]);
        $event->save();
    }
}
