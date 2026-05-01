<?php

namespace App\Services\Franchise;

use App\Jobs\SendFranchiseWebhookJob;
use App\Models\Customer;
use App\Models\FranchiseOutboxEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 母艦端「加盟夥伴狀態變化」publisher。
 *
 * 寫一筆 franchise_outbox_events（pending），並 dispatch worker job 嘗試立即送出。
 * 失敗後由 schedule(`franchise:dispatch-pending`) 兜底。
 *
 * 故意把「寫 outbox」與「送 HTTP」拆開：
 *   - observer transaction 內只 commit 寫 outbox（fast、永不 lose event）
 *   - worker 走 queue（不阻塞 admin 操作 UI）
 *
 * Shadow mode：env 不齊全（no URL / no secret）時，仍寫 outbox（以後 deploy 補齊
 * env 後 sweeper 會送出舊事件）；但 dispatch_now=true 時若 env 缺 URL 會 noop。
 */
class FranchiseEventPublisher
{
    public function __construct(
        // 給測試 / 未來 IoC 用；正式環境直接 app(Publisher::class)
    ) {}

    /**
     * 標記為加盟夥伴。
     *
     * @param  Customer  $customer  必須已 save（要有 id）
     * @param  ?string   $source     'mothership_admin' | 'order_first_purchase' | 'manual_import' …
     * @param  ?CarbonImmutable  $verifiedAt  認證時間（預設 now）
     */
    public function dispatchActivated(
        Customer $customer,
        ?string $source = null,
        ?CarbonImmutable $verifiedAt = null,
    ): FranchiseOutboxEvent {
        return $this->writeOutbox(
            customer: $customer,
            eventType: FranchiseOutboxEvent::EVENT_ACTIVATED,
            source: $source ?? 'mothership_admin',
            verifiedAt: $verifiedAt ?? CarbonImmutable::now(),
        );
    }

    /**
     * 解除加盟夥伴。退出 / 違規 / 退費取消都走這條。
     */
    public function dispatchDeactivated(
        Customer $customer,
        ?string $source = null,
    ): FranchiseOutboxEvent {
        return $this->writeOutbox(
            customer: $customer,
            eventType: FranchiseOutboxEvent::EVENT_DEACTIVATED,
            source: $source ?? 'mothership_admin',
            verifiedAt: null,
        );
    }

    private function writeOutbox(
        Customer $customer,
        string $eventType,
        string $source,
        ?CarbonImmutable $verifiedAt,
    ): FranchiseOutboxEvent {
        // 朵朵端 receiver 期望的 contract（已 prod live, PR #96）
        $data = [
            'uuid' => $customer->pandora_user_uuid,
            'email' => $customer->email,
            'source' => $source,
        ];
        if ($verifiedAt !== null) {
            $data['verified_at'] = $verifiedAt->toIso8601ZuluString();
        }

        $event = FranchiseOutboxEvent::create([
            'event_id' => (string) Str::uuid7(),
            'event_type' => $eventType,
            'customer_id' => $customer->id,
            'target_uuid' => $customer->pandora_user_uuid,
            'target_email' => $customer->email,
            'payload' => [
                'type' => $eventType,
                'data' => $data,
            ],
            'attempts' => 0,
        ]);

        // Try to send right away（worker bypass the 1m schedule wait）。
        // 失敗也沒關係 — schedule(`franchise:dispatch-pending`) 會兜底。
        try {
            SendFranchiseWebhookJob::dispatch($event->id);
        } catch (\Throwable $e) {
            Log::warning('[FranchisePublisher] dispatch failed (will retry by sweeper)', [
                'event_id' => $event->event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event;
    }
}
