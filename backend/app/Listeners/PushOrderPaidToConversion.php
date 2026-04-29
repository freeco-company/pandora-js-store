<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\PandoraConversionClient;
use App\Services\PandoraGamificationPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ADR-008 §2.3 — pushes a `mothership.first_order` (or `mothership.order_paid`
 * for repeat purchases) event to py-service when an order's payment_status
 * flips to `paid`.
 *
 * Why queued:
 *   - We must NOT block the order-paid HTTP path (ECPay callback) on a third-
 *     party POST. py-service is allowed to be down or slow; the order pipeline
 *     is not.
 *   - Queue gives us retries with backoff for free. py-service-side rate limits
 *     or transient 5xx don't lose events.
 *
 * Idempotency:
 *   - `event_id = "mothership.order.{order_id}.paid"`. py-service's
 *     `/api/v1/internal/events` is expected to dedupe on this. If the listener
 *     fires twice (e.g. two payment_status writes to 'paid' due to a bug), the
 *     downstream sees one event.
 *
 * "First order" detection:
 *   - We count earlier paid orders for the same customer with id < current.id.
 *     If zero, this is the first paid order → fire `mothership.first_order`
 *     (the段 1 → 段 2 transition signal from ADR-008 §2.2).
 *   - Otherwise → `mothership.order_paid` (a repeat-purchase signal that
 *     py-service can use for段 2 monthly aggregation).
 *
 * Failure mode:
 *   - Client noop (base_url not set) → log + return; no retry.
 *   - Client throws (HTTP 5xx / network) → bubble up so the queue retries.
 *   - tries=5, backoff exponential 30s/60s/120s/240s/480s → max ~15min total
 *     before going to failed_jobs. Ops can then replay manually.
 */
class PushOrderPaidToConversion implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    /**
     * Exponential backoff in seconds. Laravel calls back per attempt.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 240, 480];
    }

    public function __construct(
        private readonly PandoraConversionClient $client,
        private readonly PandoraGamificationPublisher $gamificationPublisher,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $order->loadMissing('customer', 'items.product');

        $customer = $order->customer;
        $uuid = $customer?->pandora_user_uuid;
        if ($uuid === null || $uuid === '') {
            // Customer not yet provisioned with an identity uuid (rare — backfill
            // command should have caught these). Log and drop; nothing to push.
            Log::info('[PushOrderPaidToConversion] customer has no pandora_user_uuid; skipping', [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
            ]);

            return;
        }

        // First-paid-order detection. We use id < current to avoid races where
        // two siblings updated 'paid' at the same instant. created_at would be
        // ambiguous; primary key ordering is monotonic.
        $earlierPaidExists = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->where('id', '<', $order->id)
            ->exists();

        $isFirstOrder = ! $earlierPaidExists;
        $eventType = $isFirstOrder ? 'mothership.first_order' : 'mothership.order_paid';

        // Build sku list from order_items → product. Some legacy items may have
        // null product_id (deleted product); just skip those.
        $skuCodes = $order->items
            ->map(fn ($item) => $item->product?->sku)
            ->filter(fn ($sku) => is_string($sku) && $sku !== '')
            ->values()
            ->all();

        $payload = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => (float) $order->total,
            'is_first_order' => $isFirstOrder,
            'sku_codes' => $skuCodes,
            'payment_method' => $order->payment_method,
        ];

        $occurredAt = ($order->updated_at ?? now())->toIso8601String();
        $eventId = 'mothership.order.'.$order->id.'.paid';

        try {
            $this->client->pushEvent(
                eventId: $eventId,
                pandoraUserUuid: $uuid,
                eventType: $eventType,
                payload: $payload,
                occurredAt: $occurredAt,
            );
        } catch (\Throwable $e) {
            // Re-throw so queue retries. We log here for traceability — once
            // tries are exhausted Laravel writes to failed_jobs which ops mirror.
            Log::warning('[PushOrderPaidToConversion] push failed; will retry', [
                'order_id' => $order->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // ADR-009 — also publish to gamification for the first paid order so
        // the customer earns the cross-app jerosse.first_order XP and the
        // conversion lifecycle's `applicant → franchisee_self_use` rule fires
        // alongside the gamification.level_up webhook chain.
        // Repeat purchases don't fire gamification here — those are scoreless
        // engagement signals (catalog has no jerosse.order_paid kind).
        if ($isFirstOrder) {
            try {
                $this->gamificationPublisher->publish(
                    pandoraUserUuid: $uuid,
                    eventKind: 'jerosse.first_order',
                    idempotencyKey: 'jerosse.order.'.$order->id.'.first',
                    metadata: [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'amount' => (float) $order->total,
                    ],
                    occurredAt: $occurredAt,
                );
            } catch (\Throwable $e) {
                // Same retry semantics as the conversion push — 5xx → re-throw,
                // 4xx soft-handled inside the publisher.
                Log::warning('[PushOrderPaidToConversion] gamification publish failed; will retry', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }
}
