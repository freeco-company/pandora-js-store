<?php

namespace Tests\Feature;

use App\Services\PandoraGamificationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PandoraGamificationPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_gamification.base_url', 'https://gam.test');
        config()->set('services.pandora_gamification.shared_secret', 'test-secret');
    }

    public function test_isConfigured_returns_false_when_base_url_empty(): void
    {
        config()->set('services.pandora_gamification.base_url', '');
        $this->assertFalse(app(PandoraGamificationPublisher::class)->isConfigured());
    }

    public function test_publish_noops_when_not_configured(): void
    {
        config()->set('services.pandora_gamification.base_url', '');
        Http::fake();

        $ok = app(PandoraGamificationPublisher::class)->publish(
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
            'jerosse.first_order',
            'idemp-1',
        );
        $this->assertFalse($ok);
        Http::assertNothingSent();
    }

    public function test_publish_drops_unknown_event_kind(): void
    {
        Http::fake();
        $ok = app(PandoraGamificationPublisher::class)->publish(
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
            'jerosse.bogus_event',
            'idemp-1',
        );
        $this->assertFalse($ok);
        Http::assertNothingSent();
    }

    public function test_publish_drops_empty_uuid_or_idempotency_key(): void
    {
        Http::fake();
        $svc = app(PandoraGamificationPublisher::class);
        $this->assertFalse($svc->publish('', 'jerosse.first_order', 'idemp-1'));
        $this->assertFalse($svc->publish('uuid', 'jerosse.first_order', ''));
        Http::assertNothingSent();
    }

    public function test_publish_posts_with_X_Internal_Secret_and_correct_payload(): void
    {
        Http::fake([
            'gam.test/*' => Http::response([
                'id' => 1, 'xp_delta' => 100, 'total_xp' => 100, 'group_level' => 2,
                'leveled_up_to' => 2, 'duplicate' => false,
            ], 201),
        ]);

        $ok = app(PandoraGamificationPublisher::class)->publish(
            pandoraUserUuid: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
            eventKind: 'jerosse.first_order',
            idempotencyKey: 'jerosse.order.42.paid',
            metadata: ['order_id' => 42, 'amount' => 6600.0],
        );

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://gam.test/api/v1/internal/gamification/events'
                && $request->hasHeader('X-Internal-Secret', 'test-secret')
                && $request['source_app'] === 'jerosse'
                && $request['event_kind'] === 'jerosse.first_order'
                && $request['idempotency_key'] === 'jerosse.order.42.paid'
                && $request['metadata']['amount'] === 6600.0;
        });
    }

    public function test_publish_returns_false_on_4xx_without_throwing(): void
    {
        Http::fake([
            'gam.test/*' => Http::response(['detail' => 'unknown event_kind'], 422),
        ]);

        $ok = app(PandoraGamificationPublisher::class)->publish(
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
            'jerosse.first_order',
            'idemp-x',
        );
        $this->assertFalse($ok);
    }

    public function test_publish_throws_on_5xx_so_queue_retries(): void
    {
        Http::fake([
            'gam.test/*' => Http::response('boom', 503),
        ]);

        $this->expectException(\RuntimeException::class);
        app(PandoraGamificationPublisher::class)->publish(
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
            'jerosse.first_order',
            'idemp-y',
        );
    }

    public function test_AchievementService_award_publishes_jerosse_achievement_awarded(): void
    {
        Http::fake([
            'gam.test/*' => Http::response([
                'awarded' => true, 'code' => 'first_order', 'tier' => 'bronze',
                'xp_delta' => 50, 'total_xp' => 50, 'group_level' => 1,
            ], 201),
        ]);

        $customer = \App\Models\Customer::create([
            'name' => 'Pub Test',
            'email' => 'pub-test@e.com',
            'phone' => '0912000111',
            'password' => bcrypt('x'),
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0099',
        ]);

        $awarded = app(\App\Services\AchievementService::class)
            ->award($customer, \App\Services\AchievementCatalog::FIRST_ORDER);

        $this->assertSame(\App\Services\AchievementCatalog::FIRST_ORDER, $awarded);
        Http::assertSent(function ($request) use ($customer) {
            return $request->url() === 'https://gam.test/api/v1/internal/gamification/events'
                && $request['event_kind'] === 'jerosse.achievement_awarded'
                && $request['source_app'] === 'jerosse'
                && $request['idempotency_key'] === 'jerosse.achievement.'.$customer->id.'.'.\App\Services\AchievementCatalog::FIRST_ORDER
                && $request['metadata']['code'] === \App\Services\AchievementCatalog::FIRST_ORDER;
        });
    }

    public function test_AchievementService_award_skips_publish_when_customer_has_no_uuid(): void
    {
        Http::fake();
        $customer = \App\Models\Customer::create([
            'name' => 'No Uuid',
            'email' => 'no-uuid@e.com',
            'phone' => '0912000222',
            'password' => bcrypt('x'),
        ]);
        $customer->forceFill(['pandora_user_uuid' => null])->saveQuietly();

        app(\App\Services\AchievementService::class)
            ->award($customer, \App\Services\AchievementCatalog::FIRST_ORDER);

        Http::assertNothingSent();
    }

    public function test_AchievementService_award_swallows_publish_5xx_to_avoid_blocking_checkout(): void
    {
        Http::fake([
            'gam.test/*' => Http::response('boom', 503),
        ]);
        $customer = \App\Models\Customer::create([
            'name' => 'Five XX',
            'email' => 'five-xx@e.com',
            'phone' => '0912000333',
            'password' => bcrypt('x'),
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0500',
        ]);

        // Should NOT throw — synchronous award path must not break checkout
        $awarded = app(\App\Services\AchievementService::class)
            ->award($customer, \App\Services\AchievementCatalog::FIRST_ORDER);

        $this->assertSame(\App\Services\AchievementCatalog::FIRST_ORDER, $awarded);
    }
}
