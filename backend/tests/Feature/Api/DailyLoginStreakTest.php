<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerDailyStreak;
use App\Services\PandoraGamificationPublisher;
use App\Services\Streak\DailyLoginStreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SPEC-cross-app-streak Phase 1.C — per-App 每日登入 streak tests（母艦）。
 *
 * 鏡像 pandora-meal/tests/Feature/Api/DailyLoginStreakTest.php，
 * 唯獨改成 Customer + jerosse.streak_7/streak_30 publish points。
 */
class DailyLoginStreakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 9, 0, 0, 'Asia/Taipei'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Tester',
            'email' => 't' . uniqid() . '@example.com',
            'phone' => '0911' . random_int(100000, 999999),
            'password' => bcrypt('x'),
        ], $attrs));
    }

    public function test_first_login_creates_streak_1_and_is_first_today_true(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        $r = $svc->recordLogin($customer);

        $this->assertSame(1, $r['streak']);
        $this->assertTrue($r['is_first_today']);
        $this->assertTrue($r['is_milestone']); // 1 in milestones
        $this->assertSame('第一天', $r['milestone_label']);
        $this->assertSame('2026-05-04', $r['today_date']);

        $row = CustomerDailyStreak::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(1, (int) $row->current_streak);
        $this->assertSame(1, (int) $row->longest_streak);
        $this->assertSame('2026-05-04', $row->last_login_date->toDateString());
    }

    public function test_second_login_same_day_is_no_op(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        $svc->recordLogin($customer);
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 18, 0, 0, 'Asia/Taipei'));
        $r = $svc->recordLogin($customer);

        $this->assertSame(1, $r['streak']);
        $this->assertFalse($r['is_first_today']);
        $this->assertFalse($r['is_milestone']);
    }

    public function test_next_day_login_increments_streak_to_2(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        $svc->recordLogin($customer);
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 9, 0, 0, 'Asia/Taipei'));
        $r = $svc->recordLogin($customer);

        $this->assertSame(2, $r['streak']);
        $this->assertTrue($r['is_first_today']);
        $this->assertFalse($r['is_milestone']); // 2 not in milestones
    }

    public function test_skipping_a_day_resets_streak_to_1(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        $svc->recordLogin($customer); // 5/4
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 9, 0, 0, 'Asia/Taipei'));
        $svc->recordLogin($customer); // 5/5 → 2
        Carbon::setTestNow(Carbon::create(2026, 5, 7, 9, 0, 0, 'Asia/Taipei'));
        $r = $svc->recordLogin($customer); // 跳 5/6 → reset

        $this->assertSame(1, $r['streak']);
        $this->assertTrue($r['is_first_today']);

        $row = CustomerDailyStreak::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(2, (int) $row->longest_streak); // longest preserved
    }

    public function test_triggers_milestone_at_all_8_points(): void
    {
        $customer = $this->makeCustomer();
        $svc = app(DailyLoginStreakService::class);

        $milestones = [1, 3, 7, 14, 21, 30, 60, 100];
        $hit = [];

        $start = Carbon::create(2026, 1, 1, 9, 0, 0, 'Asia/Taipei');
        for ($day = 0; $day < 105; $day++) {
            Carbon::setTestNow($start->copy()->addDays($day));
            $r = $svc->recordLogin($customer);
            if ($r['is_milestone']) {
                $hit[] = $r['streak'];
            }
        }

        $this->assertSame($milestones, $hit);
    }

    public function test_publish_failure_does_not_break_record_login(): void
    {
        // bind a publisher stub that throws on publish()
        $stub = new class extends PandoraGamificationPublisher
        {
            public bool $called = false;

            public function publish(
                string $pandoraUserUuid,
                string $eventKind,
                string $idempotencyKey,
                array $metadata = [],
                ?string $occurredAt = null,
            ): bool {
                $this->called = true;
                throw new \RuntimeException('simulated publish failure');
            }
        };
        app()->instance(PandoraGamificationPublisher::class, $stub);

        $customer = $this->makeCustomer(['pandora_user_uuid' => 'test-uuid-xyz']);
        $svc = app(DailyLoginStreakService::class);

        // streak=7 hits the publish path (PUBLISH_KIND_BY_STREAK)
        $start = Carbon::create(2026, 1, 1, 9, 0, 0, 'Asia/Taipei');
        for ($day = 0; $day < 7; $day++) {
            Carbon::setTestNow($start->copy()->addDays($day));
            $r = $svc->recordLogin($customer);
        }

        $this->assertSame(7, $r['streak']);
        $this->assertTrue($r['is_milestone']);
        $this->assertTrue($stub->called);
    }

    public function test_get_api_streak_today_returns_json_and_x_streak_header(): void
    {
        $customer = $this->makeCustomer();
        $token = $customer->createToken('t')->plainTextToken;

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/streak/today');

        $resp->assertOk()
            ->assertJsonPath('current_streak', 1)
            ->assertJsonPath('is_first_today', true)
            ->assertJsonPath('today_date', '2026-05-04');

        $this->assertNotNull($resp->headers->get('X-Streak'));
    }

    public function test_get_api_streak_today_is_no_op_on_second_call_same_day(): void
    {
        $customer = $this->makeCustomer();
        $token = $customer->createToken('t')->plainTextToken;
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->withHeaders($auth)->getJson('/api/streak/today')->assertOk();
        $this->withHeaders($auth)->getJson('/api/streak/today')
            ->assertOk()
            ->assertJsonPath('current_streak', 1)
            ->assertJsonPath('is_first_today', false);
    }

    public function test_get_api_streak_today_requires_auth(): void
    {
        $this->getJson('/api/streak/today')->assertStatus(401);
    }
}
