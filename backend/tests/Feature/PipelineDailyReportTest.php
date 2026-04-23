<?php

namespace Tests\Feature;

use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Smoke + scenario tests for `pipeline:daily-report`.
 *
 * --dry mode is the harness — it runs the full data-gathering and formatting
 * pipeline but prints to stdout instead of hitting Discord. Assertions target
 * the console output captured via Artisan::output(). We bypass
 * `expectsOutputToContain` because it is whitespace- and encoding-fragile
 * around Chinese substrings; raw string `str_contains` works reliably.
 *
 * Order rows are inserted via `DB::table()` because `Order::create()` strips
 * `created_at` / `updated_at` (not in $fillable) and Laravel back-fills them
 * with `now()` — which ruins back-dated fixtures for "days since conversion"
 * scenarios.
 */
class PipelineDailyReportTest extends TestCase
{
    use RefreshDatabase;

    private function runReport(): string
    {
        Artisan::call('pipeline:daily-report', ['--dry' => true]);
        return Artisan::output();
    }

    private function assertOutputContains(string $needle, string $output, string $msg = ''): void
    {
        $this->assertTrue(
            str_contains($output, $needle),
            ($msg ? "{$msg}\n" : '') . "Output missing \"{$needle}\".\nFull output:\n{$output}",
        );
    }

    public function test_dry_run_outputs_expected_sections_with_empty_data(): void
    {
        $out = $this->runReport();
        $this->assertOutputContains('管線健診', $out);
        $this->assertOutputContains('管線接通度', $out);
        $this->assertOutputContains('📈 流量', $out);
        $this->assertOutputContains('🎯 轉換漏斗', $out);
        $this->assertOutputContains('🌱 SEO', $out);
        $this->assertOutputContains('🤖 GEO', $out);
        $this->assertOutputContains('📌 今日行動建議', $out);
    }

    public function test_never_converted_shows_red_conversion_light(): void
    {
        $out = $this->runReport();
        $this->assertOutputContains('尚未首單', $out);
        $this->assertOutputContains('🔴', $out);
    }

    public function test_recent_conversion_shows_green_light(): void
    {
        DB::table('orders')->insert([
            'order_number' => 'TEST-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000, 'shipping_fee' => 0,
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(12),
        ]);

        // Sanity: did the insert land correctly?
        $dbRow = DB::table('orders')->where('order_number', 'TEST-001')->first();
        $this->assertNotNull($dbRow, 'Order row was not inserted');
        $this->assertSame('paid', $dbRow->payment_status);
        $this->assertNull($dbRow->wp_order_id);

        $out = $this->runReport();
        $this->assertOutputContains('🟢', $out);
        $this->assertMatchesRegularExpression('/昨日有單/u', $out);
    }

    public function test_new_site_tolerance_yellow_under_7_days(): void
    {
        DB::table('orders')->insert([
            'order_number' => 'TEST-002',
            'status' => 'completed',
            'payment_status' => 'paid',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000, 'shipping_fee' => 0,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $out = $this->runReport();
        $this->assertMatchesRegularExpression('/已\s*3\s*天無成交/u', $out);
        $this->assertOutputContains('新站', $out);
    }

    public function test_new_site_red_alert_over_7_days(): void
    {
        DB::table('orders')->insert([
            'order_number' => 'TEST-003',
            'status' => 'completed',
            'payment_status' => 'paid',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000, 'shipping_fee' => 0,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $out = $this->runReport();
        $this->assertMatchesRegularExpression('/連續\s*10\s*天無成交/u', $out);
        $this->assertOutputContains('超過新站容忍值', $out);
    }

    public function test_wp_imported_orders_do_not_count_as_conversions(): void
    {
        // Historical WP orders shouldn't reset the "days since conversion" clock
        DB::table('orders')->insert([
            'order_number' => 'WP-9999',
            'status' => 'completed',
            'payment_status' => 'paid',
            'wp_order_id' => 9999,
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000, 'shipping_fee' => 0,
            'created_at' => now()->subHours(1),
            'updated_at' => now()->subHours(1),
        ]);

        $out = $this->runReport();
        $this->assertOutputContains('尚未首單', $out);
    }

    public function test_ai_referral_visits_trigger_green_geo_light_and_show_in_flow(): void
    {
        $yesterday = now()->subDay()->setTime(14, 0);
        Visit::factory()->create([
            'visited_at' => $yesterday,
            'referer_source' => 'ai_referral',
            'visitor_id' => 'human-ai-1',
        ]);
        Visit::factory()->create([
            'visited_at' => $yesterday,
            'referer_source' => 'ai_referral',
            'visitor_id' => 'human-ai-2',
        ]);

        $out = $this->runReport();
        // "AI引薦" appears in flow + "AI 引薦" appears in the verdict line.
        $this->assertMatchesRegularExpression('/AI\s*引薦\s*UV：2/u', $out);
    }

    public function test_bot_visits_excluded_from_human_uv_count(): void
    {
        $yesterday = now()->subDay()->setTime(14, 0);
        Visit::factory()->create(['visited_at' => $yesterday, 'referer_source' => 'google', 'visitor_id' => 'h1']);
        Visit::factory()->create(['visited_at' => $yesterday, 'referer_source' => 'bot', 'visitor_id' => 'bot1']);
        Visit::factory()->create(['visited_at' => $yesterday, 'referer_source' => 'bot', 'visitor_id' => 'bot2']);

        $out = $this->runReport();
        $this->assertMatchesRegularExpression('/昨日\s*UV\s+1\b/u', $out);
    }

    public function test_traffic_with_zero_orders_surfaces_funnel_warning(): void
    {
        $yesterday = now()->subDay()->setTime(14, 0);
        for ($i = 0; $i < 25; $i++) {
            Visit::factory()->create([
                'visited_at' => $yesterday,
                'referer_source' => 'google',
                'visitor_id' => "h{$i}",
            ]);
        }

        $out = $this->runReport();
        $this->assertMatchesRegularExpression('/25\s*UV\s*但\s*0\s*進結帳/u', $out);
    }

    public function test_pending_orders_show_as_abandoned_checkouts(): void
    {
        DB::table('orders')->insert([
            'order_number' => 'PEND-001',
            'status' => 'pending',
            'pricing_tier' => 'regular',
            'subtotal' => 800, 'total' => 800, 'shipping_fee' => 0,
            'created_at' => now()->subDay()->setTime(12, 0),
            'updated_at' => now()->subDay()->setTime(12, 0),
        ]);
        DB::table('orders')->insert([
            'order_number' => 'COMP-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'total' => 1000, 'shipping_fee' => 0,
            'created_at' => now()->subDay()->setTime(15, 0),
            'updated_at' => now()->subDay()->setTime(15, 0),
        ]);

        $out = $this->runReport();
        $this->assertMatchesRegularExpression('/昨日進入結帳\s+2/u', $out);
        $this->assertMatchesRegularExpression('/已付款\s+1/u', $out);
        $this->assertMatchesRegularExpression('/待付款\(棄\)\s+1/u', $out);
    }

    public function test_ai_bot_crawls_surface_in_geo_section(): void
    {
        DB::table('ai_visits_daily')->insert([
            'date' => now()->subDay()->toDateString(),
            'bot_type' => 'claude',
            'source' => 'bot',
            'hits' => 150,
            'last_path' => '/',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_visits_daily')->insert([
            'date' => now()->subDay()->toDateString(),
            'bot_type' => 'gpt',
            'source' => 'bot',
            'hits' => 80,
            'last_path' => '/',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $out = $this->runReport();
        $this->assertMatchesRegularExpression('/Claude\s+150\s*hits/u', $out);
        $this->assertMatchesRegularExpression('/GPT\s+80\s*hits/u', $out);
    }
}
