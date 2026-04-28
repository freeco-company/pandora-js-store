<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIdentityOutbox;
use App\Models\OutboxIdentityEvent;
use Illuminate\Console\Command;

/**
 * php artisan mirror:dispatch-pending [--limit=200]
 *
 * 排程每 5 分鐘執行：把 pending + (next_retry_at <= now() OR null) 的
 * outbox events 丟進 queue 給 ProcessIdentityOutbox 處理。
 */
class MirrorDispatchPending extends Command
{
    protected $signature = 'mirror:dispatch-pending {--limit=200}';

    protected $description = 'Dispatch pending identity-mirror events to queue';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $events = OutboxIdentityEvent::where('status', OutboxIdentityEvent::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            ProcessIdentityOutbox::dispatch($event->id);
        }

        $this->info("Dispatched {$events->count()} identity-mirror events");

        return self::SUCCESS;
    }
}
