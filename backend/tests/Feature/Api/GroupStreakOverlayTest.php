<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Services\Streak\GroupStreakClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SPEC-cross-app-streak Phase 5B (母艦) — group master streak overlay tests.
 *
 * 驗證：
 *   - 設定齊全 + 有 uuid → /api/streak/today 回傳 `group` payload
 *   - 沒設定 base_url / shared_secret → group=null（local dev / staging）
 *   - 沒綁 Pandora Core uuid → group=null（不打網路）
 *   - py-service 5xx → group=null（fail-soft，不破壞主流程）
 *   - 30s cache：同 uuid 連續打兩次只送一次 outbound HTTP
 *   - X-Internal-Secret header 正確掛上
 */
class GroupStreakOverlayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 5, 4, 9, 0, 0, 'Asia/Taipei'));
        // Default config — individual tests override via config()->set().
        config()->set('services.pandora_gamification.base_url', 'https://py.test');
        config()->set('services.pandora_gamification.shared_secret', 'test-secret');
        config()->set('services.pandora_gamification.timeout', 5);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeCustomer(?string $uuid = null): Customer
    {
        return Customer::create([
            'name' => 'Tester',
            'email' => 't'.uniqid().'@example.com',
            'phone' => '0911'.random_int(100000, 999999),
            'password' => bcrypt('x'),
            'pandora_user_uuid' => $uuid,
        ]);
    }

    public function test_streak_today_includes_group_payload_when_configured_and_bound(): void
    {
        $customer = $this->makeCustomer('11111111-1111-1111-1111-111111111111');
        $token = $customer->createToken('t')->plainTextToken;

        Http::fake([
            'py.test/api/v1/internal/group-streak/*' => Http::response([
                'user_uuid' => '11111111-1111-1111-1111-111111111111',
                'current_streak' => 5,
                'longest_streak' => 12,
                'last_login_date' => '2026-05-04',
                'last_seen_app' => 'meal',
                'today_in_streak' => true,
            ], 200),
        ]);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/streak/today');

        $resp->assertOk()
            ->assertJsonPath('group.current_streak', 5)
            ->assertJsonPath('group.longest_streak', 12)
            ->assertJsonPath('group.last_seen_app', 'meal')
            ->assertJsonPath('group.today_in_streak', true);

        Http::assertSent(function (HttpRequest $req) {
            return str_contains($req->url(), '/internal/group-streak/11111111-1111-1111-1111-111111111111')
                && $req->header('X-Internal-Secret')[0] === 'test-secret';
        });
    }

    public function test_group_is_null_when_customer_has_no_pandora_uuid(): void
    {
        $customer = $this->makeCustomer(null);
        $token = $customer->createToken('t')->plainTextToken;

        // Even if we fake a response, controller should not call out without uuid.
        Http::fake([
            '*' => Http::response(['current_streak' => 99], 200),
        ]);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/streak/today');

        $resp->assertOk()->assertJsonPath('group', null);
        Http::assertNothingSent();
    }

    public function test_group_is_null_when_not_configured(): void
    {
        config()->set('services.pandora_gamification.base_url', '');
        config()->set('services.pandora_gamification.shared_secret', '');

        $customer = $this->makeCustomer('22222222-2222-2222-2222-222222222222');
        $token = $customer->createToken('t')->plainTextToken;

        Http::fake();

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/streak/today');

        $resp->assertOk()->assertJsonPath('group', null);
        Http::assertNothingSent();
    }

    public function test_group_is_null_when_py_service_returns_5xx(): void
    {
        $customer = $this->makeCustomer('33333333-3333-3333-3333-333333333333');
        $token = $customer->createToken('t')->plainTextToken;

        Http::fake([
            'py.test/api/v1/internal/group-streak/*' => Http::response('boom', 502),
        ]);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/streak/today');

        // Main streak flow must succeed even when overlay fails.
        $resp->assertOk()
            ->assertJsonPath('current_streak', 1)
            ->assertJsonPath('group', null);
    }

    public function test_group_streak_is_cached_for_thirty_seconds(): void
    {
        $client = app(GroupStreakClient::class);
        Http::fake([
            'py.test/api/v1/internal/group-streak/*' => Http::response([
                'current_streak' => 7,
                'longest_streak' => 7,
                'last_login_date' => '2026-05-04',
                'last_seen_app' => 'jerosse',
                'today_in_streak' => true,
            ], 200),
        ]);

        $uuid = '44444444-4444-4444-4444-444444444444';
        $first = $client->fetch($uuid);
        $second = $client->fetch($uuid);
        $third = $client->fetch($uuid);

        $this->assertSame(7, $first['current_streak']);
        $this->assertSame(7, $second['current_streak']);
        $this->assertSame(7, $third['current_streak']);
        Http::assertSentCount(1);
    }

    public function test_group_streak_caches_failure_to_avoid_request_storm(): void
    {
        $client = app(GroupStreakClient::class);
        Http::fake([
            'py.test/api/v1/internal/group-streak/*' => Http::response('down', 500),
        ]);

        $uuid = '55555555-5555-5555-5555-555555555555';
        $this->assertNull($client->fetch($uuid));
        $this->assertNull($client->fetch($uuid));

        // Second call should be served from "null cache" sentinel — only one
        // outbound HTTP attempted within the 30s window.
        Http::assertSentCount(1);
    }
}
