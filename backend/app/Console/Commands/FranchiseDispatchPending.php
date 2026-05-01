<?php

namespace App\Console\Commands;

use App\Jobs\SendFranchiseWebhookJob;
use App\Models\FranchiseOutboxEvent;
use Illuminate\Console\Command;

/**
 * php artisan franchise:dispatch-pending [--limit=200]
 *
 * 每分鐘排程：把 dispatched_at IS NULL + (next_retry_at <= now() OR null) 且
 * attempts < MAX_ATTEMPTS 的 outbox events 重新丟進 queue。
 */
class FranchiseDispatchPending extends Command
{
    protected $signature = 'franchise:dispatch-pending {--limit=200}';

    protected $description = 'Dispatch pending franchise webhook events to queue';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $maxAttempts = (int) config('services.franchise_webhook.max_attempts', FranchiseOutboxEvent::MAX_ATTEMPTS);

        $events = FranchiseOutboxEvent::whereNull('dispatched_at')
            ->where('attempts', '<', $maxAttempts)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            SendFranchiseWebhookJob::dispatch($event->id);
        }

        $this->info("Dispatched {$events->count()} franchise webhook events");

        return self::SUCCESS;
    }
}
