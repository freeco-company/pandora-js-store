<?php

namespace Tests\Feature\Identity\Cutover;

use App\Services\Identity\Cutover\CutoverGate;
use Tests\TestCase;

class CutoverGateTest extends TestCase
{
    public function test_default_mode_is_legacy_when_unset(): void
    {
        config()->set('identity.cutover_mode', null);

        $this->assertSame(CutoverGate::MODE_LEGACY, app(CutoverGate::class)->mode());
    }

    public function test_invalid_mode_falls_back_to_legacy(): void
    {
        config()->set('identity.cutover_mode', 'banana');

        $this->assertSame(CutoverGate::MODE_LEGACY, app(CutoverGate::class)->mode());
    }

    public function test_legacy_mode_never_uses_platform_as_master_or_shadows(): void
    {
        config()->set('identity.cutover_mode', 'legacy');
        $gate = app(CutoverGate::class);

        $this->assertFalse($gate->shouldUsePlatformAsMaster('a@b.com'));
        $this->assertFalse($gate->shouldShadow());
    }

    public function test_shadow_mode_shadows_but_does_not_use_platform_as_master(): void
    {
        config()->set('identity.cutover_mode', 'shadow');
        $gate = app(CutoverGate::class);

        $this->assertTrue($gate->shouldShadow());
        $this->assertFalse($gate->shouldUsePlatformAsMaster('a@b.com'));
    }

    public function test_cutover_mode_with_empty_whitelist_uses_platform_for_all(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', []);
        $gate = app(CutoverGate::class);

        $this->assertTrue($gate->shouldUsePlatformAsMaster('anyone@example.com'));
        $this->assertTrue($gate->shouldUsePlatformAsMaster(null));  // no email but flag全開
    }

    public function test_cutover_mode_with_whitelist_only_for_listed_emails(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', ['canary@example.com', 'OTHER@example.com']);
        $gate = app(CutoverGate::class);

        $this->assertTrue($gate->shouldUsePlatformAsMaster('canary@example.com'));
        $this->assertTrue($gate->shouldUsePlatformAsMaster('CANARY@example.com'));  // case-insensitive
        $this->assertTrue($gate->shouldUsePlatformAsMaster('other@example.com'));
        $this->assertFalse($gate->shouldUsePlatformAsMaster('outside@example.com'));
        $this->assertFalse($gate->shouldUsePlatformAsMaster(null));
    }

    public function test_fail_open_default_true(): void
    {
        config()->set('identity.cutover_fail_open', null);

        $this->assertTrue(app(CutoverGate::class)->failOpen());
    }
}
