<?php

namespace Tests\Feature\Conversion;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ADR-008 §2.3 — internal endpoint feeding py-service the段 2 訊號 (a)
 * "月進貨連續 N 個月 > 門檻". Coverage:
 *
 *   1. happy path: 3 月 paid 訂單匯總、newest-first、缺月補 0
 *   2. months query param 邊界（預設 3、cap 12、< 1 fallback）
 *   3. uuid not mapped → 404 reason=uuid_not_mapped
 *   4. 只算 payment_status=paid（pending / refunded 不算）
 *   5. signature mismatch → 401
 */
class ConversionMonthlyPurchasesEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-conv-secret-monthly';

    private const PATH_PREFIX = '/api/internal/conversion/customer-monthly-purchases/';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_conversion.internal_secret', self::SECRET);
        config()->set('services.pandora_conversion.window_seconds', 300);
    }

    public function test_happy_path_aggregates_paid_orders_per_month_newest_first(): void
    {
        // Pin "now" so the calendar-month math is deterministic regardless of
        // when the test actually runs.
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));

        $uuid = (string) Str::uuid();
        $customer = $this->makeCustomer($uuid);

        // 2026-04: two paid orders totalling 32500
        $this->makePaidOrder($customer->id, 12000, Carbon::parse('2026-04-02 09:00:00'));
        $this->makePaidOrder($customer->id, 20500, Carbon::parse('2026-04-10 09:00:00'));
        // 2026-03: one paid order
        $this->makePaidOrder($customer->id, 38000, Carbon::parse('2026-03-15 09:00:00'));
        // 2026-02: nothing → expect 0
        // 2026-01 (outside window if months=3): should NOT be returned
        $this->makePaidOrder($customer->id, 99999, Carbon::parse('2026-01-20 09:00:00'));
        // pending order in 04 → must NOT count
        $this->makeOrderWithStatus($customer->id, 50000, Carbon::parse('2026-04-05 09:00:00'), 'pending');

        $res = $this->getSigned(self::PATH_PREFIX.$uuid);

        $res->assertOk();
        $body = $res->json();

        $this->assertSame($uuid, $body['uuid']);
        $this->assertSame($customer->id, $body['customer_id']);
        $this->assertSame(3, $body['months_requested']);

        // Newest first: 2026-04, 2026-03, 2026-02
        $this->assertSame('2026-04', $body['monthly_totals'][0]['month']);
        $this->assertEqualsWithDelta(32500.0, $body['monthly_totals'][0]['total'], 0.001);
        $this->assertSame(2, $body['monthly_totals'][0]['order_count']);

        $this->assertSame('2026-03', $body['monthly_totals'][1]['month']);
        $this->assertEqualsWithDelta(38000.0, $body['monthly_totals'][1]['total'], 0.001);
        $this->assertSame(1, $body['monthly_totals'][1]['order_count']);

        $this->assertSame('2026-02', $body['monthly_totals'][2]['month']);
        $this->assertEqualsWithDelta(0.0, $body['monthly_totals'][2]['total'], 0.001);
        $this->assertSame(0, $body['monthly_totals'][2]['order_count']);

        Carbon::setTestNow();
    }

    public function test_months_query_param_clamps_to_max_12(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));

        $uuid = (string) Str::uuid();
        $this->makeCustomer($uuid);

        $res = $this->getSigned(self::PATH_PREFIX.$uuid.'?months=99');

        $res->assertOk();
        $this->assertSame(12, $res->json('months_requested'));
        $this->assertCount(12, $res->json('monthly_totals'));

        Carbon::setTestNow();
    }

    public function test_months_param_under_1_falls_back_to_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));

        $uuid = (string) Str::uuid();
        $this->makeCustomer($uuid);

        $res = $this->getSigned(self::PATH_PREFIX.$uuid.'?months=0');

        $res->assertOk();
        $this->assertSame(3, $res->json('months_requested'));

        Carbon::setTestNow();
    }

    public function test_unmapped_uuid_returns_404_with_reason(): void
    {
        $uuid = (string) Str::uuid();

        $res = $this->getSigned(self::PATH_PREFIX.$uuid);

        $res->assertStatus(404);
        $res->assertJson(['reason' => 'uuid_not_mapped']);
    }

    public function test_signature_mismatch_returns_401(): void
    {
        $uuid = (string) Str::uuid();
        $this->makeCustomer($uuid);

        $res = $this->call('GET', self::PATH_PREFIX.$uuid, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PANDORA_TIMESTAMP' => (string) time(),
            'HTTP_X_PANDORA_SIGNATURE' => 'definitely-wrong',
        ]);

        $res->assertStatus(401);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function makeCustomer(string $uuid): Customer
    {
        return Customer::create([
            'pandora_user_uuid' => $uuid,
            'name' => 'Test',
            'email' => 'monthly-'.$uuid.'@example.com',
            'password' => bcrypt('x'),
        ]);
    }

    private function makePaidOrder(int $customerId, float $total, Carbon $createdAt): Order
    {
        return $this->makeOrderRaw($customerId, $total, $createdAt, status: 'completed', paymentStatus: 'paid');
    }

    private function makeOrderWithStatus(int $customerId, float $total, Carbon $createdAt, string $paymentStatus): Order
    {
        return $this->makeOrderRaw($customerId, $total, $createdAt, status: 'processing', paymentStatus: $paymentStatus);
    }

    private function makeOrderRaw(int $customerId, float $total, Carbon $createdAt, string $status, string $paymentStatus): Order
    {
        $order = Order::create([
            'order_number' => 'TEST-'.Str::random(10),
            'customer_id' => $customerId,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'pricing_tier' => 'regular',
            'subtotal' => $total,
            'shipping_fee' => 0,
            'total' => $total,
        ]);
        \DB::table('orders')->where('id', $order->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order->fresh();
    }

    private function getSigned(string $pathWithQuery)
    {
        // Sign path WITHOUT query string — matches VerifyConversionInternalSignature
        // which signs $request->getPathInfo().
        $pathOnly = explode('?', $pathWithQuery, 2)[0];
        $ts = (string) time();
        $base = $ts.'.GET.'.$pathOnly;
        $sig = hash_hmac('sha256', $base, self::SECRET);

        return $this->call('GET', $pathWithQuery, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PANDORA_TIMESTAMP' => $ts,
            'HTTP_X_PANDORA_SIGNATURE' => $sig,
        ]);
    }
}
