<?php

namespace Tests\Feature;

use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tracks the source-bucket logic in VisitController. The decision tree
 * (click_source → utm_source+utm_medium → referer host → direct) is the
 * single thing that separates paid from organic in every dashboard stat,
 * so mis-bucketing silently under-reports ROI on ads. These tests lock
 * each branch.
 */
class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function track(array $payload): void
    {
        $this->postJson('/api/track/visit', $payload)->assertOk();
    }

    public function test_gclid_forces_google_ads_bucket(): void
    {
        $this->track([
            'session_id' => 'sess-a',
            'path' => '/products/foo',
            'referer_url' => 'https://www.google.com/',
            'click_id' => 'CjwK...',
            'click_source' => 'google_ads',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('google_ads', Visit::first()->referer_source);
    }

    public function test_fbclid_forces_facebook_ads_bucket(): void
    {
        $this->track([
            'session_id' => 'sess-b',
            'path' => '/',
            'referer_url' => 'https://l.facebook.com/',
            'click_id' => 'IwAR123',
            'click_source' => 'facebook_ads',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('facebook_ads', Visit::first()->referer_source);
    }

    public function test_utm_medium_cpc_without_click_id_still_paid(): void
    {
        // Non-auto-tagged campaigns (manual utm tagging) should still be
        // classified as paid — catches old-school UTM conventions.
        $this->track([
            'session_id' => 'sess-c',
            'path' => '/',
            'referer_url' => 'https://www.google.com/',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring_sale',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('google_ads', Visit::first()->referer_source);
    }

    public function test_google_referer_without_paid_signals_stays_organic(): void
    {
        $this->track([
            'session_id' => 'sess-d',
            'path' => '/',
            'referer_url' => 'https://www.google.com/',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('google', Visit::first()->referer_source);
    }

    public function test_no_referer_is_direct(): void
    {
        $this->track([
            'session_id' => 'sess-e',
            'path' => '/',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('direct', Visit::first()->referer_source);
    }

    public function test_ua_parsing_populates_device_os_browser(): void
    {
        $this->track([
            'session_id' => 'sess-f',
            'path' => '/',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1',
        ]);

        $v = Visit::first();
        $this->assertSame('mobile', $v->device_type);
        $this->assertStringContainsString('iOS', (string) $v->os);
        $this->assertSame('Safari', $v->browser);
    }
}
