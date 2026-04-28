<?php

namespace Tests\Feature\Identity;

use App\Models\Customer;
use App\Models\IdentityWebhookNonce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret-12345';

    private const URL = '/api/internal/identity/webhook';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_core.webhook_secret', self::SECRET);
        config()->set('services.pandora_core.webhook_window_seconds', 300);
    }

    public function test_valid_signature_creates_customer(): void
    {
        $body = $this->buildBody('user.upserted', [
            'uuid' => '019dd1c9-0a76-7304-af1c-e5bf7eb98d07',
            'display_name' => 'Test User',
            'email_canonical' => 'test@example.com',
            'phone_canonical' => '0911222333',
            'identities' => [
                ['type' => 'email', 'value' => 'test@example.com', 'is_primary' => true],
            ],
        ]);

        $res = $this->postWithSig($body);

        $res->assertOk();
        $this->assertDatabaseHas('customers', [
            'pandora_user_uuid' => '019dd1c9-0a76-7304-af1c-e5bf7eb98d07',
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_missing_headers_rejected_401(): void
    {
        $body = $this->buildBody('user.upserted', ['uuid' => 'a-uuid']);

        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body)
            ->assertStatus(401);
    }

    public function test_wrong_signature_rejected_401(): void
    {
        $body = $this->buildBody('user.upserted', ['uuid' => 'a-uuid']);
        $eventId = 'evt-1';
        $ts = (string) time();

        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PANDORA_EVENT_ID' => $eventId,
            'HTTP_X_PANDORA_TIMESTAMP' => $ts,
            'HTTP_X_PANDORA_SIGNATURE' => 'wrong-signature-12345',
        ], $body)
            ->assertStatus(401);
    }

    public function test_timestamp_too_old_rejected_401(): void
    {
        $body = $this->buildBody('user.upserted', ['uuid' => 'a-uuid']);
        $oldTs = (string) (time() - 600);  // 10 min ago, window=300
        $eventId = 'evt-old';
        $sig = hash_hmac('sha256', "{$oldTs}.{$eventId}.{$body}", self::SECRET);

        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PANDORA_EVENT_ID' => $eventId,
            'HTTP_X_PANDORA_TIMESTAMP' => $oldTs,
            'HTTP_X_PANDORA_SIGNATURE' => $sig,
        ], $body)
            ->assertStatus(401);
    }

    public function test_replay_same_event_id_returns_200_noop(): void
    {
        $body = $this->buildBody('user.upserted', [
            'uuid' => '019dd1c9-0a76-7304-af1c-aaaaaaaaaaaa',
            'display_name' => 'First',
            'email_canonical' => 'replay@example.com',
        ]);
        $eventId = 'evt-replay-test';

        // First time
        $this->postWithSig($body, $eventId)->assertOk();
        $this->assertSame(1, Customer::count());

        // Replay — 200 noop, customer 不變
        $res = $this->postWithSig($body, $eventId);
        $res->assertOk();
        $res->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, Customer::count());
        $this->assertSame(1, IdentityWebhookNonce::count());
    }

    public function test_missing_secret_config_returns_500(): void
    {
        config()->set('services.pandora_core.webhook_secret', '');
        $body = $this->buildBody('user.upserted', ['uuid' => 'a-uuid']);

        $this->postWithSig($body)->assertStatus(500);
    }

    public function test_unknown_event_type_returns_422(): void
    {
        $body = $this->buildBody('weird.thing', ['uuid' => 'a-uuid']);

        $this->postWithSig($body)->assertStatus(422);
    }

    public function test_payload_without_data_returns_422(): void
    {
        $body = json_encode([
            'event_id' => 'evt-1',
            'type' => 'user.upserted',
            'occurred_at' => now()->toIso8601String(),
            'data' => [],
        ]);

        $this->postWithSig($body)->assertStatus(422);
    }

    private function buildBody(string $type, array $data): string
    {
        return (string) json_encode([
            'event_id' => 'inner-event',
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function postWithSig(string $body, string $eventId = 'evt-default')
    {
        $ts = (string) time();
        $sig = hash_hmac('sha256', "{$ts}.{$eventId}.{$body}", self::SECRET);

        return $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PANDORA_EVENT_ID' => $eventId,
            'HTTP_X_PANDORA_TIMESTAMP' => $ts,
            'HTTP_X_PANDORA_SIGNATURE' => $sig,
        ], $body);
    }
}
