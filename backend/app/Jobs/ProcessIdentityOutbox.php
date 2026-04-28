<?php

namespace App\Jobs;

use App\Models\OutboxIdentityEvent;
use App\Services\Identity\PandoraCoreClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 處理一筆 outbox event：呼叫 Pandora Core internal endpoint，
 * 成功標 sent / 失敗依 status 決定 retry vs dead_letter。
 *
 * dispatch 來源（Step 1 階段）：
 *   - schedule(`mirror:dispatch-pending`) 每 5 分鐘掃 pending+到期的
 *   - 也可在 IdentityMirrorService 寫 outbox 後直接 dispatch（縮短延遲）
 *
 * Backoff: 1m → 5m → 15m → 1h → 6h → dead_letter (5 次)
 */
class ProcessIdentityOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BACKOFF_SECONDS = [60, 300, 900, 3600, 21600];

    public function __construct(public readonly int $eventId) {}

    public function handle(PandoraCoreClient $client): void
    {
        /** @var ?OutboxIdentityEvent $event */
        $event = OutboxIdentityEvent::find($this->eventId);
        if ($event === null || $event->status !== OutboxIdentityEvent::STATUS_PENDING) {
            return;
        }

        $response = $client->customerUpsert($event->payload);

        if ($response->success) {
            $event->status = OutboxIdentityEvent::STATUS_SENT;
            $event->sent_at = now();
            $event->last_error = null;
            $event->save();

            return;
        }

        $event->retry_count++;
        $event->last_error = "[{$response->status}] {$response->error}";

        // misconfigured (status=0) → 留 pending 不前進 retry_count（環境問題）
        if ($response->status === 0) {
            $event->retry_count--;  // undo
            $event->next_retry_at = now()->addMinutes(5);
            $event->save();
            Log::warning('[IdentityOutbox] Misconfigured client', ['event_id' => $event->id, 'error' => $response->error]);

            return;
        }

        if (! $response->retryable || $event->retry_count >= OutboxIdentityEvent::MAX_RETRIES) {
            $event->status = OutboxIdentityEvent::STATUS_DEAD_LETTER;
            $event->save();
            Log::error('[IdentityOutbox] Dead letter', [
                'event_id' => $event->id,
                'status' => $response->status,
                'retry_count' => $event->retry_count,
                'error' => $response->error,
            ]);

            return;
        }

        $idx = min($event->retry_count - 1, count(self::BACKOFF_SECONDS) - 1);
        $event->next_retry_at = now()->addSeconds(self::BACKOFF_SECONDS[$idx]);
        $event->save();
    }
}
