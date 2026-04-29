<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ADR-009 Phase B inbound — py-service → 母艦 gamification webhook tests.
 *
 * Covers signature verification, replay-protection (event_id dedup), and the
 * three mirror handlers (group_level / achievement / outfit).
 */
class GamificationWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-test-secret';
    private const URI = '/api/internal/gamification/webhook';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_gamification.webhook_secret', self::SECRET);
        config()->set('services.pandora_gamification.webhook_window_seconds', 300);
    }

    private function makeCustomer(string $uuid): Customer
    {
        return Customer::create([
            'name' => 'Test '.substr($uuid, 0, 6),
            'email' => 'webhook+'.uniqid().'@e.com',
            'phone' => '0911'.rand(100000, 999999),
            'password' => bcrypt('x'),
            'pandora_user_uuid' => $uuid,
        ]);
    }

    private function signedHeaders(string $body, ?string $tsOverride = null): array
    {
        $ts = $tsOverride ?? now()->toIso8601String();
        $nonce = bin2hex(random_bytes(16));
        $sig = 'sha256='.hash_hmac('sha256', "{$ts}.{$nonce}.{$body}", self::SECRET);

        return [
            'X-Pandora-Timestamp' => $ts,
            'X-Pandora-Nonce' => $nonce,
            'X-Pandora-Signature' => $sig,
            'Content-Type' => 'application/json',
        ];
    }

    public function test_unsigned_request_401(): void
    {
        $resp = $this->postJson(self::URI, [
            'event_id' => 'e-1', 'event_type' => 'gamification.level_up',
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
            'payload' => ['new_level' => 5],
        ]);
        $resp->assertStatus(401);
    }

    public function test_missing_secret_500(): void
    {
        config()->set('services.pandora_gamification.webhook_secret', '');
        $resp = $this->postJson(self::URI, []);
        $resp->assertStatus(500);
    }

    public function test_signature_mismatch_401(): void
    {
        $body = json_encode(['event_id' => 'e-2', 'event_type' => 'gamification.level_up', 'pandora_user_uuid' => 'x', 'payload' => []]);
        $headers = $this->signedHeaders($body);
        $headers['X-Pandora-Signature'] = 'sha256=deadbeef';
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($headers), $body);
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_timestamp_out_of_window_401(): void
    {
        $body = json_encode(['event_id' => 'e-3', 'event_type' => 'gamification.level_up', 'pandora_user_uuid' => 'x', 'payload' => []]);
        $oldTs = now()->subHour()->toIso8601String();
        $headers = $this->signedHeaders($body, $oldTs);
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($headers), $body);
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_level_up_mirrors_group_level_on_customer(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee1001';
        $customer = $this->makeCustomer($uuid);
        $body = json_encode([
            'event_id' => 'lvl-1',
            'event_type' => 'gamification.level_up',
            'pandora_user_uuid' => $uuid,
            'payload' => [
                'new_level' => 5,
                'total_xp' => 300,
                'level_name_zh' => '進階期',
                'level_name_en' => 'Advanced',
            ],
        ]);
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $resp->assertStatus(200);
        $resp->assertJson(['mirrored' => true]);

        $customer->refresh();
        $this->assertSame(5, $customer->group_level);
        $this->assertSame(300, $customer->group_level_xp);
        $this->assertSame('進階期', $customer->group_level_name_zh);
    }

    public function test_level_up_does_not_regress(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee1002';
        $customer = $this->makeCustomer($uuid);
        $customer->update(['group_level' => 7, 'group_level_xp' => 500]);

        $body = json_encode([
            'event_id' => 'lvl-2',
            'event_type' => 'gamification.level_up',
            'pandora_user_uuid' => $uuid,
            'payload' => ['new_level' => 3, 'total_xp' => 100],
        ]);
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $resp->assertStatus(200);
        $resp->assertJson(['mirrored' => false]);

        $this->assertSame(7, $customer->fresh()->group_level);
    }

    public function test_achievement_awarded_creates_achievement_row(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee1003';
        $customer = $this->makeCustomer($uuid);
        $body = json_encode([
            'event_id' => 'ach-1',
            'event_type' => 'gamification.achievement_awarded',
            'pandora_user_uuid' => $uuid,
            'payload' => ['code' => 'meal.streak_7', 'tier' => 'bronze', 'source_app' => 'meal'],
        ]);
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $resp->assertStatus(200)->assertJson(['mirrored' => true]);

        $this->assertDatabaseHas('achievements', [
            'customer_id' => $customer->id,
            'code' => 'meal.streak_7',
        ]);
    }

    public function test_outfit_unlocked_merges_codes_idempotently(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee1004';
        $customer = $this->makeCustomer($uuid);
        $customer->update(['outfits_owned' => ['none', 'scarf']]);

        $body = json_encode([
            'event_id' => 'out-1',
            'event_type' => 'gamification.outfit_unlocked',
            'pandora_user_uuid' => $uuid,
            'payload' => ['codes' => ['scarf', 'wings'], 'awarded_via' => 'level_up'],
        ]);
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $resp->assertStatus(200)->assertJson(['mirrored' => 1]);  // wings new; scarf skipped

        $owned = $customer->fresh()->outfits_owned;
        $this->assertContains('scarf', $owned);
        $this->assertContains('wings', $owned);
    }

    public function test_duplicate_event_id_short_circuits_200(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee1005';
        $this->makeCustomer($uuid);

        $body = json_encode([
            'event_id' => 'dup-1',
            'event_type' => 'gamification.level_up',
            'pandora_user_uuid' => $uuid,
            'payload' => ['new_level' => 2, 'total_xp' => 100],
        ]);

        // First time
        $r1 = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $r1->assertStatus(200)->assertJson(['mirrored' => true]);

        // Second time, fresh signature, same event_id
        $r2 = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $r2->assertStatus(200)->assertJson(['status' => 'duplicate', 'event_id' => 'dup-1']);
    }

    public function test_unknown_event_type_returns_200_ignored(): void
    {
        $body = json_encode([
            'event_id' => 'unk-1',
            'event_type' => 'gamification.future_thing',
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee1006',
            'payload' => [],
        ]);
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $resp->assertStatus(200)->assertJson(['status' => 'ignored']);
    }

    public function test_unknown_uuid_silently_drops_with_200(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee9999';
        // No customer with this uuid
        $body = json_encode([
            'event_id' => 'orphan-1',
            'event_type' => 'gamification.level_up',
            'pandora_user_uuid' => $uuid,
            'payload' => ['new_level' => 5, 'total_xp' => 300],
        ]);
        $resp = $this->call('POST', self::URI, [], [], [], $this->headersToServer($this->signedHeaders($body)), $body);
        $resp->assertStatus(200)->assertJson(['mirrored' => false]);
    }

    /** @param array<string, string> $headers */
    private function headersToServer(array $headers): array
    {
        $server = [];
        foreach ($headers as $k => $v) {
            $key = 'HTTP_'.strtoupper(str_replace('-', '_', $k));
            $server[$key] = $v;
        }
        // Content-Type is special-cased
        if (isset($headers['Content-Type'])) {
            $server['CONTENT_TYPE'] = $headers['Content-Type'];
            unset($server['HTTP_CONTENT_TYPE']);
        }

        return $server;
    }
}
