<?php

namespace Tests\Feature\Identity\Cutover;

use App\Models\Customer;
use App\Services\Identity\Cutover\CutoverOAuthService;
use App\Services\Identity\Cutover\PlatformOAuthBridge;
use App\Services\Identity\PandoraCoreClient;
use App\Services\Identity\PandoraCoreResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CutoverOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_legacy_mode_creates_customer_locally_no_platform_call(): void
    {
        config()->set('identity.cutover_mode', 'legacy');

        $this->mockClient()->shouldNotReceive('customerUpsert');

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-1', 'a@example.com', 'Alice');

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('g-1', $customer->google_id);
        $this->assertSame('a@example.com', $customer->email);
        $this->assertNull($customer->pandora_user_uuid);
    }

    public function test_legacy_mode_finds_existing_by_google_id(): void
    {
        config()->set('identity.cutover_mode', 'legacy');
        $existing = Customer::create([
            'name' => 'old',
            'email' => 'old@example.com',
            'google_id' => 'g-1',
            'password' => bcrypt('x'),
        ]);

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-1', 'old@example.com', 'name');

        $this->assertSame($existing->id, $customer->id);
    }

    public function test_shadow_mode_writes_local_and_calls_platform_writes_uuid(): void
    {
        config()->set('identity.cutover_mode', 'shadow');

        $this->mockClient()
            ->shouldReceive('customerUpsert')
            ->once()
            ->andReturn(PandoraCoreResponse::ok(['uuid' => 'platform-uuid-123']));

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-shadow', 'shadow@example.com', 'Shadow');

        $this->assertSame('platform-uuid-123', $customer->pandora_user_uuid);
        $this->assertSame('g-shadow', $customer->google_id);
    }

    public function test_shadow_mode_platform_failure_is_silent(): void
    {
        config()->set('identity.cutover_mode', 'shadow');

        $this->mockClient()
            ->shouldReceive('customerUpsert')
            ->once()
            ->andReturn(PandoraCoreResponse::failed(503, 'down'));

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-fail', 'fail@example.com', 'Fail');

        // legacy 路徑仍然成功
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('g-fail', $customer->google_id);
        $this->assertNull($customer->pandora_user_uuid);
    }

    public function test_cutover_mode_uses_platform_uuid_and_attaches_to_local(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', []);  // 全開

        $this->mockClient()
            ->shouldReceive('customerUpsert')
            ->once()
            ->andReturn(PandoraCoreResponse::ok(['uuid' => 'cut-uuid-1']));

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-cut', 'cut@example.com', 'Cut');

        $this->assertSame('cut-uuid-1', $customer->pandora_user_uuid);
    }

    public function test_cutover_mode_whitelist_excluded_user_does_not_call_platform(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', ['canary@example.com']);

        $this->mockClient()->shouldNotReceive('customerUpsert');

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-out', 'outside@example.com', 'Outside');

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertNull($customer->pandora_user_uuid);
    }

    public function test_cutover_mode_fail_open_falls_back_to_legacy(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', []);
        config()->set('identity.cutover_fail_open', true);

        $this->mockClient()
            ->shouldReceive('customerUpsert')
            ->once()
            ->andReturn(PandoraCoreResponse::failed(500, 'down'));

        // 不該 throw，要拿到 legacy customer
        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-down', 'down@example.com', 'Down');

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('g-down', $customer->google_id);
        $this->assertNull($customer->pandora_user_uuid);
    }

    public function test_cutover_mode_fail_closed_throws(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', []);
        config()->set('identity.cutover_fail_open', false);

        $this->mockClient()
            ->shouldReceive('customerUpsert')
            ->once()
            ->andReturn(PandoraCoreResponse::failed(500, 'down'));

        $this->expectException(\RuntimeException::class);

        app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-strict', 'strict@example.com', 'Strict');
    }

    public function test_existing_uuid_not_overwritten_on_match(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', []);

        // local already has uuid
        Customer::create([
            'name' => 'old',
            'email' => 'pre@example.com',
            'google_id' => 'g-pre',
            'pandora_user_uuid' => 'same-uuid',
            'password' => bcrypt('x'),
        ]);

        $this->mockClient()
            ->shouldReceive('customerUpsert')
            ->once()
            ->andReturn(PandoraCoreResponse::ok(['uuid' => 'same-uuid']));

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-pre', 'pre@example.com', 'old');

        $this->assertSame('same-uuid', $customer->pandora_user_uuid);
    }

    public function test_uuid_mismatch_does_not_overwrite_local(): void
    {
        config()->set('identity.cutover_mode', 'cutover');
        config()->set('identity.cutover_whitelist', []);

        Customer::create([
            'name' => 'old',
            'email' => 'pre@example.com',
            'google_id' => 'g-pre',
            'pandora_user_uuid' => 'local-uuid',
            'password' => bcrypt('x'),
        ]);

        $this->mockClient()
            ->shouldReceive('customerUpsert')
            ->once()
            ->andReturn(PandoraCoreResponse::ok(['uuid' => 'different-uuid']));

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('google', 'g-pre', 'pre@example.com', 'old');

        // 不覆寫，保留本地原 uuid
        $customer->refresh();
        $this->assertSame('local-uuid', $customer->pandora_user_uuid);
    }

    public function test_line_callback_with_null_email_uses_fallback_email(): void
    {
        config()->set('identity.cutover_mode', 'legacy');

        $customer = app(CutoverOAuthService::class)
            ->loginOrCreate('line', 'line-no-email', null, 'LINE 會員');

        $this->assertSame('line-no-email@line.user', $customer->email);
        $this->assertSame('line-no-email', $customer->line_id);
    }

    private function mockClient(): MockInterface
    {
        $mock = Mockery::mock(PandoraCoreClient::class);
        $this->app->instance(PandoraCoreClient::class, $mock);

        // 把 bridge 重新解析確保拿到 mock
        $this->app->forgetInstance(PlatformOAuthBridge::class);
        $this->app->forgetInstance(CutoverOAuthService::class);

        return $mock;
    }
}
