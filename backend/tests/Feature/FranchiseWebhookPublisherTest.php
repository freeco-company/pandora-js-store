<?php

namespace Tests\Feature;

use App\Jobs\SendFranchiseWebhookJob;
use App\Models\Customer;
use App\Models\FranchiseOutboxEvent;
use App\Services\Franchise\FranchiseEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the 母艦 → 朵朵 (pandora-meal) franchise webhook publisher chain:
 *   1. Service writes outbox + dispatches job
 *   2. Job signs HMAC + posts correct payload shape
 *   3. CustomerObserver fires publisher on is_franchisee toggle
 *   4. Retry / dead-letter behaviour
 */
class FranchiseWebhookPublisherTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Alice Tester',
            'email' => 'alice@example.com',
            'phone' => '0912345678',
            'pandora_user_uuid' => '01HXY000000000000000000ABC',
            'password' => 'secret-pass',
        ], $overrides));
    }

    public function test_publisher_writes_activated_outbox_row_with_correct_payload(): void
    {
        Bus::fake();
        $customer = $this->makeCustomer();
        $verifiedAt = CarbonImmutable::parse('2026-05-01T12:34:56Z');

        app(FranchiseEventPublisher::class)
            ->dispatchActivated($customer, source: 'mothership_admin', verifiedAt: $verifiedAt);

        $this->assertSame(1, FranchiseOutboxEvent::count());
        /** @var FranchiseOutboxEvent $event */
        $event = FranchiseOutboxEvent::first();

        $this->assertSame('franchisee.activated', $event->event_type);
        $this->assertSame($customer->id, $event->customer_id);
        $this->assertSame('alice@example.com', $event->target_email);
        $this->assertSame('01HXY000000000000000000ABC', $event->target_uuid);
        $this->assertNull($event->dispatched_at);
        $this->assertSame(0, $event->attempts);
        $this->assertNotEmpty($event->event_id);
        $this->assertSame(36, strlen($event->event_id));

        // Payload contract — must match 朵朵 receiver expectations
        $this->assertSame('franchisee.activated', $event->payload['type']);
        $this->assertSame('alice@example.com', $event->payload['data']['email']);
        $this->assertSame('01HXY000000000000000000ABC', $event->payload['data']['uuid']);
        $this->assertSame('mothership_admin', $event->payload['data']['source']);
        $this->assertSame('2026-05-01T12:34:56Z', $event->payload['data']['verified_at']);

        Bus::assertDispatched(SendFranchiseWebhookJob::class);
    }

    public function test_publisher_writes_deactivated_outbox_without_verified_at(): void
    {
        Bus::fake();
        $customer = $this->makeCustomer();

        app(FranchiseEventPublisher::class)->dispatchDeactivated($customer);

        /** @var FranchiseOutboxEvent $event */
        $event = FranchiseOutboxEvent::first();
        $this->assertSame('franchisee.deactivated', $event->event_type);
        $this->assertArrayNotHasKey('verified_at', $event->payload['data']);
    }

    public function test_observer_fires_publisher_when_is_franchisee_toggles_on(): void
    {
        Bus::fake();
        $customer = $this->makeCustomer();
        // baseline: creating customer does NOT fire franchise event
        $this->assertSame(0, FranchiseOutboxEvent::count());

        $customer->update(['is_franchisee' => true]);

        $this->assertSame(1, FranchiseOutboxEvent::count());
        $this->assertSame(
            'franchisee.activated',
            FranchiseOutboxEvent::first()->event_type,
        );
    }

    public function test_observer_fires_deactivated_when_is_franchisee_toggles_off(): void
    {
        Bus::fake();
        $customer = $this->makeCustomer(['is_franchisee' => true, 'franchisee_verified_at' => now()]);
        $customer->update(['is_franchisee' => false]);

        $events = FranchiseOutboxEvent::orderBy('id')->get();
        $this->assertSame(1, $events->count());
        $this->assertSame('franchisee.deactivated', $events->first()->event_type);
    }

    public function test_observer_does_not_fire_when_other_columns_change(): void
    {
        Bus::fake();
        $customer = $this->makeCustomer();
        $customer->update(['name' => 'Renamed', 'phone' => '0900999888']);
        $this->assertSame(0, FranchiseOutboxEvent::count());
    }

    public function test_job_signs_hmac_and_posts_correct_headers(): void
    {
        config([
            'services.franchise_webhook.url' => 'https://meal-api.example.com/api/internal/franchisee/webhook',
            'services.franchise_webhook.secret' => 'shared-secret-test',
        ]);

        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        $customer = $this->makeCustomer();
        $event = FranchiseOutboxEvent::create([
            'event_id' => '01HXAAAAAAAAAAAAAAAAAAAA01',
            'event_type' => 'franchisee.activated',
            'customer_id' => $customer->id,
            'target_uuid' => $customer->pandora_user_uuid,
            'target_email' => $customer->email,
            'payload' => [
                'type' => 'franchisee.activated',
                'data' => ['uuid' => $customer->pandora_user_uuid, 'email' => $customer->email, 'source' => 'mothership_admin'],
            ],
            'attempts' => 0,
        ]);

        (new SendFranchiseWebhookJob($event->id))->handle();

        $event->refresh();
        $this->assertNotNull($event->dispatched_at);
        $this->assertSame('200', $event->last_status_code);

        Http::assertSent(function ($request) use ($event) {
            // Headers
            $eventIdHeader = $request->header('X-Pandora-Event-Id')[0] ?? null;
            $tsHeader = $request->header('X-Pandora-Timestamp')[0] ?? null;
            $sigHeader = $request->header('X-Pandora-Signature')[0] ?? null;

            if ($eventIdHeader !== $event->event_id) {
                return false;
            }
            if (! ctype_digit((string) $tsHeader)) {
                return false;
            }
            // Signature recompute
            $body = $request->body();
            $expected = hash_hmac('sha256', $tsHeader.'.'.$event->event_id.'.'.$body, 'shared-secret-test');

            return hash_equals($expected, (string) $sigHeader);
        });
    }

    public function test_job_increments_attempts_and_sets_backoff_on_5xx(): void
    {
        config([
            'services.franchise_webhook.url' => 'https://meal-api.example.com/api/internal/franchisee/webhook',
            'services.franchise_webhook.secret' => 'shared-secret-test',
        ]);
        Http::fake([
            '*' => Http::response('boom', 500),
        ]);

        $customer = $this->makeCustomer();
        $event = FranchiseOutboxEvent::create([
            'event_id' => '01HXBBBBBBBBBBBBBBBBBBBB01',
            'event_type' => 'franchisee.activated',
            'customer_id' => $customer->id,
            'target_uuid' => $customer->pandora_user_uuid,
            'target_email' => $customer->email,
            'payload' => ['type' => 'franchisee.activated', 'data' => []],
            'attempts' => 0,
        ]);

        (new SendFranchiseWebhookJob($event->id))->handle();

        $event->refresh();
        $this->assertNull($event->dispatched_at);
        $this->assertSame(1, $event->attempts);
        $this->assertSame('500', $event->last_status_code);
        $this->assertNotNull($event->next_retry_at);
    }

    public function test_job_dead_letters_after_max_attempts(): void
    {
        config([
            'services.franchise_webhook.url' => 'https://meal-api.example.com/api/internal/franchisee/webhook',
            'services.franchise_webhook.secret' => 'shared-secret-test',
            'services.franchise_webhook.max_attempts' => 5,
        ]);
        Http::fake([
            '*' => Http::response('boom', 500),
        ]);

        $customer = $this->makeCustomer();
        $event = FranchiseOutboxEvent::create([
            'event_id' => '01HXCCCCCCCCCCCCCCCCCCCC01',
            'event_type' => 'franchisee.activated',
            'customer_id' => $customer->id,
            'payload' => ['type' => 'franchisee.activated', 'data' => []],
            'attempts' => 4,  // 一次 handle 就會踩第 5 次
        ]);

        (new SendFranchiseWebhookJob($event->id))->handle();

        $event->refresh();
        $this->assertSame(5, $event->attempts);
        $this->assertNull($event->dispatched_at);
        $this->assertNull($event->next_retry_at);  // dead letter — 停 retry
    }

    public function test_job_noop_when_misconfigured_secret_missing(): void
    {
        config([
            'services.franchise_webhook.url' => 'https://meal-api.example.com/webhook',
            'services.franchise_webhook.secret' => '',
        ]);
        Http::fake();  // 確保沒有 outbound HTTP

        $customer = $this->makeCustomer();
        $event = FranchiseOutboxEvent::create([
            'event_id' => '01HXDDDDDDDDDDDDDDDDDDDD01',
            'event_type' => 'franchisee.activated',
            'customer_id' => $customer->id,
            'payload' => ['type' => 'franchisee.activated', 'data' => []],
            'attempts' => 0,
        ]);

        (new SendFranchiseWebhookJob($event->id))->handle();

        $event->refresh();
        // misconfig 不前進 attempts，等 sweeper 之後再試
        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->dispatched_at);
        $this->assertNotNull($event->next_retry_at);
        Http::assertNothingSent();
    }
}
