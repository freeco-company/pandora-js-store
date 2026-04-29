<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdentityReconcileCmdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.pandora_core.base_url', 'https://identity.test');
        config()->set('services.pandora_core.internal_secret', 'test-secret');
        Cache::forget('identity:reconcile:cursor');
    }

    public function test_errors_out_when_env_not_configured(): void
    {
        config()->set('services.pandora_core.base_url', '');
        $this->assertSame(1, Artisan::call('identity:reconcile'));
    }

    public function test_updates_existing_customer_display_name(): void
    {
        $customer = Customer::create([
            'name' => 'Old Name',
            'email' => 'r1@e.com',
            'phone' => '0911111111',
            'password' => bcrypt('x'),
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
        ]);

        Http::fake([
            'identity.test/*' => Http::response([
                'users' => [['id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001', 'display_name' => 'New Name', 'status' => 'active', 'updated_at' => now()->toIso8601String()]],
                'next_cursor' => null, 'has_more' => false, 'count' => 1,
            ], 200),
        ]);

        Artisan::call('identity:reconcile');

        $this->assertSame('New Name', $customer->fresh()->name);
    }

    public function test_does_not_create_new_customer_from_reconcile(): void
    {
        // 母艦 doesn't auto-create customers from reconcile — only refreshes existing
        Http::fake([
            'identity.test/*' => Http::response([
                'users' => [['id' => 'orphan-uuid-no-customer', 'display_name' => 'Ghost', 'status' => 'active', 'updated_at' => now()->toIso8601String()]],
                'next_cursor' => null, 'has_more' => false, 'count' => 1,
            ], 200),
        ]);

        Artisan::call('identity:reconcile');

        $this->assertSame(0, Customer::where('pandora_user_uuid', 'orphan-uuid-no-customer')->count());
    }

    public function test_dry_run_does_not_write(): void
    {
        $customer = Customer::create([
            'name' => 'Original',
            'email' => 'r2@e.com',
            'phone' => '0922222222',
            'password' => bcrypt('x'),
            'pandora_user_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0002',
        ]);

        Http::fake([
            'identity.test/*' => Http::response([
                'users' => [['id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0002', 'display_name' => 'Changed', 'status' => 'active', 'updated_at' => now()->toIso8601String()]],
                'next_cursor' => null, 'has_more' => false, 'count' => 1,
            ], 200),
        ]);

        Artisan::call('identity:reconcile', ['--dry-run' => true]);

        $this->assertSame('Original', $customer->fresh()->name);
    }

    public function test_sends_internal_secret_header(): void
    {
        Http::fake([
            'identity.test/*' => Http::response(['users' => [], 'next_cursor' => null, 'has_more' => false, 'count' => 0], 200),
        ]);

        Artisan::call('identity:reconcile');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'identity.test/api/internal/reconcile/users')
            && $request->hasHeader('X-Pandora-Internal-Secret', 'test-secret'));
    }

    public function test_persists_cursor_for_next_run(): void
    {
        Http::fake([
            'identity.test/*' => Http::response(['users' => [], 'next_cursor' => null, 'has_more' => false, 'count' => 0], 200),
        ]);

        Artisan::call('identity:reconcile');

        $this->assertNotNull(Cache::get('identity:reconcile:cursor'));
    }

    public function test_non_2xx_fails(): void
    {
        Http::fake([
            'identity.test/*' => Http::response(['detail' => 'unauthorized'], 401),
        ]);

        $this->assertSame(1, Artisan::call('identity:reconcile'));
    }
}
