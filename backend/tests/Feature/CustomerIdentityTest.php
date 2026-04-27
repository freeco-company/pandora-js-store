<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_customer_writes_all_identity_fields_to_table(): void
    {
        $c = Customer::create([
            'name' => 'A', 'email' => 'a@example.com', 'phone' => '0911000001',
            'google_id' => 'G1', 'line_id' => 'L1',
            'password' => bcrypt('x'),
        ]);

        $this->assertSame(4, $c->identities()->count());
        $this->assertTrue($c->identities()->where('type', 'email')->where('value', 'a@example.com')->exists());
        $this->assertTrue($c->identities()->where('type', 'phone')->where('value', '0911000001')->exists());
        $this->assertTrue($c->identities()->where('type', 'google_id')->where('value', 'G1')->exists());
        $this->assertTrue($c->identities()->where('type', 'line_id')->where('value', 'L1')->exists());
    }

    public function test_oauth_identities_marked_verified_email_and_phone_not(): void
    {
        $c = Customer::create([
            'name' => 'B', 'email' => 'b@example.com', 'phone' => '0911000002',
            'google_id' => 'G2', 'password' => bcrypt('x'),
        ]);

        $email = $c->identities()->where('type', 'email')->first();
        $google = $c->identities()->where('type', 'google_id')->first();
        $phone = $c->identities()->where('type', 'phone')->first();

        $this->assertNull($email->verified_at, 'email needs explicit verification flow');
        $this->assertNotNull($google->verified_at, 'google_id is OAuth-verified by definition');
        $this->assertNull($phone->verified_at, 'phone needs OTP verification (not implemented yet)');
    }

    public function test_updating_email_demotes_old_keeps_for_history_promotes_new(): void
    {
        $c = Customer::create([
            'name' => 'C', 'email' => 'old@example.com', 'phone' => '0911000003',
            'password' => bcrypt('x'),
        ]);
        $c->update(['email' => 'new@example.com']);

        $old = CustomerIdentity::where('type', 'email')->where('value', 'old@example.com')->first();
        $new = CustomerIdentity::where('type', 'email')->where('value', 'new@example.com')->first();

        $this->assertNotNull($old, 'old email kept for history');
        $this->assertFalse($old->is_primary, 'old email demoted');
        $this->assertSame($c->id, $old->customer_id);

        $this->assertNotNull($new, 'new email created');
        $this->assertTrue($new->is_primary);
    }

    public function test_updating_phone_deletes_old_does_not_keep_history(): void
    {
        $c = Customer::create([
            'name' => 'D', 'email' => 'd@example.com', 'phone' => '0911000004',
            'password' => bcrypt('x'),
        ]);
        $c->update(['phone' => '0911000099']);

        $this->assertFalse(
            CustomerIdentity::where('type', 'phone')->where('value', '0911000004')->exists(),
            'old phone deleted (phone changes are typically corrections, not historical)'
        );
        $this->assertTrue(
            CustomerIdentity::where('type', 'phone')->where('value', '0911000099')->exists()
        );
    }

    public function test_findByIdentity_returns_owning_customer(): void
    {
        $c1 = Customer::create([
            'name' => 'E', 'email' => 'e@example.com', 'phone' => '0911000005',
            'google_id' => 'GE', 'password' => bcrypt('x'),
        ]);
        Customer::create([
            'name' => 'F', 'email' => 'f@example.com', 'password' => bcrypt('x'),
        ]);

        $this->assertSame($c1->id, Customer::findByIdentity('google_id', 'GE')?->id);
        $this->assertSame($c1->id, Customer::findByIdentity('email', 'e@example.com')?->id);
        $this->assertNull(Customer::findByIdentity('google_id', 'nonexistent'));
        $this->assertNull(Customer::findByIdentity('email', null));
    }

    public function test_identity_uniqueness_across_customers_logs_and_does_not_throw(): void
    {
        Customer::create([
            'name' => 'G', 'email' => 'g@example.com', 'google_id' => 'CONFLICT',
            'password' => bcrypt('x'),
        ]);

        // 第二個 customer 拿同樣 google_id（不該發生在正常流程，但測 observer 的容錯）
        $c2 = Customer::create([
            'name' => 'H', 'email' => 'h@example.com', 'google_id' => 'CONFLICT',
            'password' => bcrypt('x'),
        ]);

        // customers 表有兩列（google_id 在 customers 表非 unique，是已知的歷史問題）
        $this->assertSame(2, Customer::count());
        // 但 customer_identities 的 google_id=CONFLICT 只會有一筆，屬於先建的 customer
        $this->assertSame(1, CustomerIdentity::where('type', 'google_id')->where('value', 'CONFLICT')->count());
        $this->assertNull(
            $c2->identities()->where('type', 'google_id')->first(),
            'conflict skipped, does not raise'
        );
    }

    public function test_backfill_command_populates_identities_for_existing_customers(): void
    {
        // 模擬「升級前已有的 customer」— 直接 INSERT 不走 model 以避開 observer
        \DB::table('customers')->insert([
            'name' => 'Pre',
            'email' => 'pre@example.com',
            'phone' => '0911000050',
            'google_id' => 'PRE_GOOGLE',
            'password' => bcrypt('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, CustomerIdentity::count());

        $this->artisan('customer:backfill-identities')
            ->expectsOutputToContain('inserted=3')
            ->assertSuccessful();

        $this->assertSame(3, CustomerIdentity::count());
    }

    public function test_backfill_dry_run_does_not_insert(): void
    {
        \DB::table('customers')->insert([
            'name' => 'Dry', 'email' => 'dry@example.com',
            'password' => bcrypt('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('customer:backfill-identities', ['--dry' => true])
            ->assertSuccessful();

        $this->assertSame(0, CustomerIdentity::count());
    }

    public function test_findByIdentity_falls_back_to_customers_column_when_identities_empty(): void
    {
        // 模擬「升級前已存在的 customer」— 直接 INSERT 不走 model（避開 observer 寫 identities）
        \DB::table('customers')->insert([
            'id' => 999,
            'name' => 'Pre-Backfill', 'email' => 'old@example.com',
            'google_id' => 'OLD_G', 'phone' => '0911000777',
            'password' => bcrypt('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, CustomerIdentity::count(), 'identities 確實是空的');

        // 仍能由 customers 欄位 fallback 查到
        $byEmail = Customer::findByIdentity('email', 'old@example.com');
        $byGoogle = Customer::findByIdentity('google_id', 'OLD_G');
        $byPhone = Customer::findByIdentity('phone', '0911000777');

        $this->assertNotNull($byEmail);
        $this->assertNotNull($byGoogle);
        $this->assertNotNull($byPhone);
        $this->assertSame(999, $byEmail->id);
        $this->assertSame(999, $byGoogle->id);
    }

    public function test_findByIdentity_finds_via_historical_email_after_change(): void
    {
        // 客人改 email 後舊 email 仍是 identities 表上的記錄（observer 標 is_primary=false 但保留）
        $c = Customer::create([
            'name' => 'C', 'email' => 'first@example.com', 'password' => bcrypt('x'),
        ]);
        $c->update(['email' => 'second@example.com']);

        $this->assertNotNull(Customer::findByIdentity('email', 'first@example.com'));
        $this->assertSame($c->id, Customer::findByIdentity('email', 'first@example.com')->id);
        // 新 email 同樣找得到
        $this->assertSame($c->id, Customer::findByIdentity('email', 'second@example.com')->id);
    }

    public function test_findByIdentity_returns_null_for_unknown_value(): void
    {
        $this->assertNull(Customer::findByIdentity('email', 'never@example.com'));
        $this->assertNull(Customer::findByIdentity('google_id', null));
        // unknown type 也回 null（不 throw）
        $this->assertNull(Customer::findByIdentity('not_a_real_type', 'whatever'));
    }
}
