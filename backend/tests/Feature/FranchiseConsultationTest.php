<?php

namespace Tests\Feature;

use App\Models\FranchiseConsultation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ADR-008 §2.2 段 1 訊號 — `mothership.consultation_submitted`. Coverage:
 *   1. valid submission persists row + returns 202
 *   2. invalid submission returns 422
 *   3. with uuid → py-service event pushed
 *   4. without uuid → py-service NOT pushed (lifecycle FSM keys by uuid)
 *   5. Discord notify fired (best-effort, soft-fail)
 *   6. py-service down → still persists + Discord still notifies (decoupled)
 */
class FranchiseConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_conversion.base_url', 'https://py-service.test');
        config()->set('services.pandora_conversion.internal_secret', 'test-conv-secret');
        // Discord defaults to webhook unset → noop, which is fine for tests.
    }

    public function test_valid_submission_persists_and_returns_202(): void
    {
        Http::fake();

        $res = $this->postJson('/api/franchise/consultation', [
            'name' => 'Alice',
            'phone' => '0911-222-333',
            'email' => 'alice@example.com',
            'source' => 'homepage_banner',
            'note' => 'Interested in self-use',
        ]);

        $res->assertStatus(202)
            ->assertJsonPath('data.received', true);
        $this->assertGreaterThan(0, (int) $res->json('data.consultation_id'));

        $this->assertSame(1, FranchiseConsultation::count());
        $row = FranchiseConsultation::first();
        $this->assertSame('Alice', $row->name);
        $this->assertSame('0911-222-333', $row->phone);
        $this->assertSame('alice@example.com', $row->email);
        $this->assertSame('homepage_banner', $row->source);
        $this->assertSame('new', $row->status);
    }

    public function test_invalid_submission_returns_422(): void
    {
        $this->postJson('/api/franchise/consultation', [])
            ->assertStatus(422);
        $this->assertSame(0, FranchiseConsultation::count());
    }

    public function test_with_uuid_pushes_pyservice_event(): void
    {
        Http::fake([
            'py-service.test/*' => Http::response(['ok' => true], 202),
        ]);

        $this->postJson('/api/franchise/consultation', [
            'name' => 'Bob',
            'phone' => '0922-000-111',
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
            'source' => 'pandora_meal_paywall',
        ])->assertStatus(202);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return ($body['event_type'] ?? null) === 'mothership.consultation_submitted'
                && ($body['pandora_user_uuid'] ?? null) === 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001'
                && ($body['payload']['source'] ?? null) === 'pandora_meal_paywall';
        });
    }

    public function test_without_uuid_does_NOT_push_pyservice(): void
    {
        Http::fake([
            'py-service.test/*' => Http::response(['ok' => true], 202),
        ]);

        $this->postJson('/api/franchise/consultation', [
            'name' => 'Charlie',
            'phone' => '0933-000-222',
        ])->assertStatus(202);

        // Anonymous — no uuid → no lifecycle FSM event
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'py-service.test'));
        // Row still persisted
        $this->assertSame(1, FranchiseConsultation::count());
    }

    public function test_pyservice_5xx_does_NOT_break_form_submission(): void
    {
        Http::fake([
            'py-service.test/*' => Http::response('boom', 503),
        ]);

        $res = $this->postJson('/api/franchise/consultation', [
            'name' => 'Dora',
            'phone' => '0944-000-333',
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0009',
        ]);

        $res->assertStatus(202);
        $this->assertSame(1, FranchiseConsultation::count());
    }

    public function test_discord_webhook_called_when_configured(): void
    {
        config()->set('services.discord.franchise_webhook', 'https://discord.test/hook');
        Http::fake();

        $this->postJson('/api/franchise/consultation', [
            'name' => 'Erin',
            'phone' => '0955-000-444',
        ])->assertStatus(202);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'discord.test/hook'));
    }
}
