<?php

namespace Tests\Feature;

use App\Models\CartEvent;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutFunnelReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedFunnel(int $cart, int $checkout, int $attempts, int $failed, int $purchases, bool $internal = false): void
    {
        for ($i = 0; $i < $cart; $i++) {
            Visit::create([
                'visitor_id' => "v$i", 'session_id' => "s$i",
                'ip' => '1.1.1.1', 'device_type' => 'mobile',
                'os' => 'iOS', 'browser' => 'Safari',
                'referer_source' => 'direct', 'path' => '/cart',
                'is_internal' => $internal, 'visited_at' => now(),
            ]);
        }
        for ($i = 0; $i < $checkout; $i++) {
            Visit::create([
                'visitor_id' => "vc$i", 'session_id' => "s$i",
                'ip' => '1.1.1.1', 'device_type' => 'mobile',
                'os' => 'iOS', 'browser' => 'Safari',
                'referer_source' => 'direct', 'path' => '/checkout',
                'is_internal' => $internal, 'visited_at' => now(),
            ]);
        }
        for ($i = 0; $i < $attempts; $i++) {
            CartEvent::create([
                'session_id' => "s$i", 'event_type' => 'checkout_submit_attempt',
                'is_internal' => $internal, 'occurred_at' => now(),
            ]);
        }
        for ($i = 0; $i < $failed; $i++) {
            CartEvent::create([
                'session_id' => "s$i", 'event_type' => 'checkout_submit_failed',
                'is_internal' => $internal, 'occurred_at' => now(),
            ]);
        }
        for ($i = 0; $i < $purchases; $i++) {
            CartEvent::create([
                'session_id' => "s$i", 'event_type' => 'purchase',
                'is_internal' => $internal, 'occurred_at' => now(),
            ]);
        }
    }

    public function test_high_submit_failure_rate_triggers_red_diagnosis(): void
    {
        // 10 checkout sessions, 10 submit attempts, 5 failed (50% fail rate ≥ 30% threshold)
        $this->seedFunnel(cart: 10, checkout: 10, attempts: 10, failed: 5, purchases: 5);

        $this->artisan('analytics:checkout-funnel', ['--dry' => true])
            ->expectsOutputToContain('Submit failure rate')
            ->assertSuccessful();
    }

    public function test_form_abandonment_triggers_orange_diagnosis(): void
    {
        // 10 checkout sessions, only 2 submit attempts (20% < 40% threshold)
        $this->seedFunnel(cart: 10, checkout: 10, attempts: 2, failed: 0, purchases: 2);

        $this->artisan('analytics:checkout-funnel', ['--dry' => true])
            ->expectsOutputToContain('進到結帳頁不送出')
            ->assertSuccessful();
    }

    public function test_healthy_funnel_passes_green(): void
    {
        // 10 checkout sessions, 8 submit attempts, 0 failures, 8 purchases
        $this->seedFunnel(cart: 10, checkout: 10, attempts: 8, failed: 0, purchases: 8);

        $this->artisan('analytics:checkout-funnel', ['--dry' => true])
            ->expectsOutputToContain('漏斗健康')
            ->assertSuccessful();
    }

    public function test_internal_traffic_excluded(): void
    {
        // 100 internal sessions all over the place — should NOT push the
        // sample over the small-sample threshold.
        $this->seedFunnel(cart: 100, checkout: 100, attempts: 100, failed: 100, purchases: 0, internal: true);

        $this->artisan('analytics:checkout-funnel', ['--dry' => true])
            ->expectsOutputToContain('樣本太小')
            ->assertSuccessful();
    }

    public function test_dry_does_not_send_discord(): void
    {
        $this->seedFunnel(cart: 5, checkout: 5, attempts: 5, failed: 0, purchases: 5);

        // No webhook configured + --dry → just a clean exit, no http call.
        $this->artisan('analytics:checkout-funnel', ['--dry' => true])
            ->assertSuccessful();
    }
}
