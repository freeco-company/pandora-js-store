<?php

namespace Tests\Feature\Identity;

use App\Models\Customer;
use App\Services\Identity\Webhook\IdentityUpsertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 直接測 service 層，避免再過 middleware 簽章檢查 — 那邊另一支 test 涵蓋。
 */
class IdentityUpsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_customer_when_uuid_unseen(): void
    {
        app(IdentityUpsertService::class)->upsert([
            'uuid' => 'uuid-new-1',
            'display_name' => '新客戶',
            'email_canonical' => 'new@example.com',
            'phone_canonical' => '0911000111',
            'identities' => [
                ['type' => 'email', 'value' => 'new@example.com', 'is_primary' => true],
                ['type' => 'google', 'value' => 'google-sub-1', 'is_primary' => true],
            ],
        ]);

        $c = Customer::where('pandora_user_uuid', 'uuid-new-1')->first();
        $this->assertNotNull($c);
        $this->assertSame('新客戶', $c->name);
        $this->assertSame('new@example.com', $c->email);
        $this->assertSame('0911000111', $c->phone);
        $this->assertSame('google-sub-1', $c->google_id);
    }

    public function test_does_not_overwrite_existing_non_null_pii_or_address(): void
    {
        $c = Customer::create([
            'name' => '原名字',
            'email' => 'old@example.com',
            'phone' => '0900111222',
            'password' => bcrypt('x'),
            'address_city' => '台北',
            'address_district' => '中正',
            'address_detail' => '寶慶路 1 號',
            'line_id' => 'line-existing',
            'pandora_user_uuid' => 'uuid-existing',
        ]);

        app(IdentityUpsertService::class)->upsert([
            'uuid' => 'uuid-existing',
            'display_name' => '新名字',
            'email_canonical' => 'new@example.com',  // 嘗試覆蓋
            'phone_canonical' => '0922222222',       // 嘗試覆蓋
            'identities' => [
                ['type' => 'line', 'value' => 'line-new', 'is_primary' => true],
            ],
        ]);

        $c->refresh();
        // 既有非空欄位保留
        $this->assertSame('原名字', $c->name);
        $this->assertSame('old@example.com', $c->email);
        $this->assertSame('0900111222', $c->phone);
        $this->assertSame('台北', $c->address_city);
        $this->assertSame('中正', $c->address_district);
        $this->assertSame('寶慶路 1 號', $c->address_detail);
        $this->assertSame('line-existing', $c->line_id);
    }

    public function test_idempotent_repeated_upserts_dont_create_duplicates(): void
    {
        $payload = [
            'uuid' => 'uuid-idem',
            'display_name' => 'X',
            'email_canonical' => 'x@example.com',
            'identities' => [['type' => 'email', 'value' => 'x@example.com', 'is_primary' => true]],
        ];

        app(IdentityUpsertService::class)->upsert($payload);
        app(IdentityUpsertService::class)->upsert($payload);
        app(IdentityUpsertService::class)->upsert($payload);

        $this->assertSame(1, Customer::where('pandora_user_uuid', 'uuid-idem')->count());
    }

    public function test_matches_existing_customer_by_email_when_uuid_unseen(): void
    {
        $c = Customer::create([
            'name' => 'Matched',
            'email' => 'matched@example.com',
            'password' => bcrypt('x'),
            'pandora_user_uuid' => null,
        ]);

        app(IdentityUpsertService::class)->upsert([
            'uuid' => 'uuid-fill-in',
            'email_canonical' => 'matched@example.com',
            'identities' => [],
        ]);

        $c->refresh();
        $this->assertSame('uuid-fill-in', $c->pandora_user_uuid);
        $this->assertSame(1, Customer::count());
    }

    public function test_does_not_match_when_existing_has_different_uuid(): void
    {
        Customer::create([
            'name' => 'Other',
            'email' => 'shared@example.com',
            'password' => bcrypt('x'),
            'pandora_user_uuid' => 'uuid-already-claimed',
        ]);

        $result = app(IdentityUpsertService::class)->upsert([
            'uuid' => 'uuid-different',
            'email_canonical' => 'shared@example.com',  // 同 email 但不同 uuid → conflict
            'identities' => [],
        ]);

        // 衝突情境：跳過 + log，不應該把既有 customer 搶走
        $this->assertNull($result);
        $this->assertSame(1, Customer::count());
        $this->assertSame('uuid-already-claimed', Customer::first()->pandora_user_uuid);
    }

    public function test_throws_on_missing_uuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(IdentityUpsertService::class)->upsert([
            'display_name' => 'no uuid',
        ]);
    }

    public function test_provider_type_mapping_platform_to_local(): void
    {
        app(IdentityUpsertService::class)->upsert([
            'uuid' => 'uuid-mapping',
            'email_canonical' => 'e@e.com',
            'identities' => [
                ['type' => 'google', 'value' => 'g-1', 'is_primary' => true],
                ['type' => 'line', 'value' => 'l-1', 'is_primary' => true],
                ['type' => 'email', 'value' => 'e@e.com', 'is_primary' => true],
            ],
        ]);

        $c = Customer::where('pandora_user_uuid', 'uuid-mapping')->first();
        $this->assertNotNull($c);
        // customer_identities should have local-type names
        $types = $c->identities()->pluck('type')->sort()->values()->all();
        $this->assertSame(['email', 'google_id', 'line_id'], $types);
        $this->assertSame('g-1', $c->google_id);
        $this->assertSame('l-1', $c->line_id);
    }
}
