<?php

namespace Tests\Feature\Conversion;

use App\Events\OrderPaid;
use App\Listeners\PushOrderPaidToConversion;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ADR-008 §2.3 — order-paid → py-service event push. Coverage:
 *
 *   1. payment_status transition fires the OrderPaid event
 *   2. listener pushes "mothership.first_order" with is_first_order=true
 *      when no previous paid orders exist for the customer
 *   3. listener pushes "mothership.order_paid" with is_first_order=false
 *      for repeat purchases
 *   4. listener noops gracefully when base_url is not configured
 *      (dev/local should never break the order flow)
 *   5. transient HTTP failure bubbles up so the queue retries
 */
class PushOrderPaidToConversionTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-push-secret';

    private const BASE = 'https://py-service.test';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_conversion.internal_secret', self::SECRET);
        config()->set('services.pandora_conversion.base_url', self::BASE);
        config()->set('services.pandora_conversion.app_id', 'fairy_pandora');
    }

    public function test_payment_status_transition_dispatches_order_paid_event(): void
    {
        Event::fake([OrderPaid::class]);

        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer->id, paymentStatus: 'pending');

        $order->update(['payment_status' => 'paid']);

        Event::assertDispatched(OrderPaid::class, fn ($e) => $e->order->id === $order->id);
    }

    public function test_listener_pushes_first_order_when_no_prior_paid_exists(): void
    {
        Http::fake([
            self::BASE.'/api/v1/internal/events' => Http::response(['ok' => true], 202),
        ]);

        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer->id, paymentStatus: 'paid');

        app(PushOrderPaidToConversion::class)->handle(new OrderPaid($order));

        Http::assertSent(function ($request) use ($order, $customer) {
            $body = json_decode($request->body(), true);

            return $request->url() === self::BASE.'/api/v1/internal/events'
                && $request->hasHeader('X-Pandora-Signature')
                && $request->hasHeader('X-Pandora-Timestamp')
                && $body['event_type'] === 'mothership.first_order'
                && $body['pandora_user_uuid'] === $customer->pandora_user_uuid
                && $body['app_id'] === 'fairy_pandora'
                && $body['payload']['order_id'] === $order->id
                && $body['payload']['is_first_order'] === true
                && $body['event_id'] === 'mothership.order.'.$order->id.'.paid';
        });
    }

    public function test_listener_pushes_order_paid_for_repeat_purchase(): void
    {
        Http::fake([
            self::BASE.'/api/v1/internal/events' => Http::response(['ok' => true], 202),
        ]);

        $customer = $this->makeCustomer();
        // Pre-existing paid order — must be present BEFORE the new one for the
        // "is this the first paid order" check (which uses id < current.id).
        $this->makeOrder($customer->id, paymentStatus: 'paid');
        $newOrder = $this->makeOrder($customer->id, paymentStatus: 'paid');

        app(PushOrderPaidToConversion::class)->handle(new OrderPaid($newOrder));

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['event_type'] === 'mothership.order_paid'
                && $body['payload']['is_first_order'] === false;
        });
    }

    public function test_listener_noops_when_base_url_not_configured(): void
    {
        config()->set('services.pandora_conversion.base_url', null);
        Http::fake(); // any HTTP attempt would be recorded — we expect none

        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer->id, paymentStatus: 'paid');

        // Must NOT throw, must NOT make an HTTP call.
        app(PushOrderPaidToConversion::class)->handle(new OrderPaid($order));

        Http::assertNothingSent();
    }

    public function test_listener_throws_on_5xx_so_queue_retries(): void
    {
        Http::fake([
            self::BASE.'/api/v1/internal/events' => Http::response(['error' => 'boom'], 503),
        ]);

        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer->id, paymentStatus: 'paid');

        $this->expectException(\RuntimeException::class);
        app(PushOrderPaidToConversion::class)->handle(new OrderPaid($order));
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'pandora_user_uuid' => (string) Str::uuid(),
            'name' => 'Test',
            'email' => 'push-'.Str::random(6).'@example.com',
            'password' => bcrypt('x'),
        ]);
    }

    private function makeOrder(int $customerId, string $paymentStatus): Order
    {
        return Order::create([
            'order_number' => 'TEST-'.Str::random(10),
            'customer_id' => $customerId,
            'status' => 'completed',
            'payment_status' => $paymentStatus,
            'pricing_tier' => 'regular',
            'subtotal' => 6800,
            'shipping_fee' => 0,
            'total' => 6800,
            'payment_method' => 'ecpay_credit',
        ]);
    }
}
