<?php

namespace Tests\Feature\Conversion;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ADR-003 §3.2 — internal endpoint feeding py-service's
 * `HttpMothershipOrderClient`. Coverage:
 *
 *   1. happy path: 90d window math + total + last_order_at
 *   2. signature mismatch → 401 (fail closed)
 *   3. uuid not mapped to a customer → 404 with `reason=uuid_not_mapped`
 *   4. 90d boundary: 89d ago counts, 91d ago doesn't
 */
class ConversionOrdersEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-conv-secret-abc';

    private const PATH_PREFIX = '/api/internal/conversion/customer-orders/';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_conversion.internal_secret', self::SECRET);
        config()->set('services.pandora_conversion.window_seconds', 300);
    }

    public function test_happy_path_returns_recent_total_and_last_order(): void
    {
        $uuid = (string) Str::uuid();
        $customer = $this->makeCustomer($uuid);

        // 2 orders inside 90d (one processing, one completed),
        // 1 order outside 90d (completed),
        // 1 cancelled order inside 90d (must NOT count).
        $this->makeOrder($customer->id, 'completed', Carbon::now()->subDays(10));
        $this->makeOrder($customer->id, 'processing', Carbon::now()->subDays(40));
        $this->makeOrder($customer->id, 'completed', Carbon::now()->subDays(120));
        $this->makeOrder($customer->id, 'cancelled', Carbon::now()->subDays(5));

        $res = $this->getSigned(self::PATH_PREFIX.$uuid);

        $res->assertOk();
        $body = $res->json();
        $this->assertSame($uuid, $body['pandora_user_uuid']);
        $this->assertSame(2, $body['recent_orders_90d']);
        $this->assertSame(3, $body['total_orders']);
        $this->assertNotNull($body['last_order_at']);
    }

    public function test_signature_mismatch_returns_401(): void
    {
        $uuid = (string) Str::uuid();
        $this->makeCustomer($uuid);

        $ts = (string) time();
        $res = $this->call('GET', self::PATH_PREFIX.$uuid, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PANDORA_TIMESTAMP' => $ts,
            'HTTP_X_PANDORA_SIGNATURE' => 'definitely-wrong',
        ]);

        $res->assertStatus(401);
    }

    public function test_unmapped_uuid_returns_404_with_reason(): void
    {
        $uuid = (string) Str::uuid();
        // Note: no customer created for this uuid.

        $res = $this->getSigned(self::PATH_PREFIX.$uuid);

        $res->assertStatus(404);
        $res->assertJson(['reason' => 'uuid_not_mapped']);
    }

    public function test_90d_window_boundary(): void
    {
        $uuid = (string) Str::uuid();
        $customer = $this->makeCustomer($uuid);

        // Inside the window (89 days ago) — counts as recent.
        $this->makeOrder($customer->id, 'completed', Carbon::now()->subDays(89));
        // Outside the window (91 days ago) — only counts toward lifetime.
        $this->makeOrder($customer->id, 'completed', Carbon::now()->subDays(91));

        $res = $this->getSigned(self::PATH_PREFIX.$uuid);

        $res->assertOk();
        $this->assertSame(1, $res->json('recent_orders_90d'));
        $this->assertSame(2, $res->json('total_orders'));
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function makeCustomer(string $uuid): Customer
    {
        return Customer::create([
            'pandora_user_uuid' => $uuid,
            'name' => 'Test',
            'email' => 'conv-'.$uuid.'@example.com',
            'password' => bcrypt('x'),
        ]);
    }

    private function makeOrder(int $customerId, string $status, Carbon $createdAt): Order
    {
        $order = Order::create([
            'order_number' => 'TEST-'.Str::random(10),
            'customer_id' => $customerId,
            'status' => $status,
            'pricing_tier' => 'regular',
            'subtotal' => 1000,
            'shipping_fee' => 0,
            'total' => 1000,
        ]);
        // created_at is auto-managed; we need to backdate it for the 90d test.
        $order->created_at = $createdAt;
        $order->updated_at = $createdAt;
        $order->saveQuietly(['timestamps' => false]);
        // saveQuietly doesn't disable timestamps by itself; force an UPDATE
        // with timestamps = false using the query builder.
        \DB::table('orders')->where('id', $order->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order->fresh();
    }

    private function getSigned(string $path)
    {
        $ts = (string) time();
        $base = $ts.'.GET.'.$path;
        $sig = hash_hmac('sha256', $base, self::SECRET);

        return $this->call('GET', $path, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PANDORA_TIMESTAMP' => $ts,
            'HTTP_X_PANDORA_SIGNATURE' => $sig,
        ]);
    }
}
