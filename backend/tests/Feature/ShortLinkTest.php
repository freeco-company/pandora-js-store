<?php

namespace Tests\Feature;

use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    private function link(array $overrides = []): ShortLink
    {
        return ShortLink::create(array_merge([
            'code' => 'abc123',
            'target_url' => 'https://example.com/bundles/x?utm_source=ig',
            'label' => 'test',
        ], $overrides));
    }

    public function test_resolve_returns_target_url_and_increments_count(): void
    {
        $link = $this->link();

        $this->getJson('/api/short-links/abc123/resolve')
            ->assertOk()
            ->assertJson(['url' => 'https://example.com/bundles/x?utm_source=ig']);

        $this->assertSame(1, $link->fresh()->click_count);
    }

    public function test_resolve_unknown_code_returns_null(): void
    {
        $this->getJson('/api/short-links/missing9/resolve')
            ->assertOk()
            ->assertJson(['url' => null]);
    }

    public function test_resolve_expired_link_returns_null_and_does_not_increment(): void
    {
        $link = $this->link(['expires_at' => now()->subDay()]);

        $this->getJson('/api/short-links/abc123/resolve')
            ->assertOk()
            ->assertJson(['url' => null]);

        $this->assertSame(0, $link->fresh()->click_count);
    }

    public function test_route_rejects_codes_outside_length_or_charset(): void
    {
        // Too short — route regex {3,40} fails out as 404 before controller.
        $this->get('/api/short-links/ab/resolve')->assertNotFound();
        // Underscore not in [A-Za-z0-9-] — also 404.
        $this->get('/api/short-links/abc_xyz/resolve')->assertNotFound();
    }

    public function test_generate_unique_code_returns_unused_lowercase_alnum(): void
    {
        $code = ShortLink::generateUniqueCode();
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6,8}$/', $code);
        $this->assertFalse(ShortLink::where('code', $code)->exists());
    }
}
