<?php

namespace Tests\Feature;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_discount_returns_value(): void
    {
        Coupon::create(['code' => 'SAVE100', 'type' => 'fixed', 'value' => 100, 'is_active' => true]);

        $res = $this->postJson('/api/coupons/validate', [
            'code' => 'SAVE100', 'subtotal' => 500,
        ]);

        $res->assertOk();
        $this->assertEquals(100, $res->json('discount'));
    }

    public function test_percentage_discount_is_calculated(): void
    {
        Coupon::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);

        $res = $this->postJson('/api/coupons/validate', [
            'code' => 'TEN', 'subtotal' => 1000,
        ]);

        $res->assertOk()->assertJsonPath('discount', 100);
    }

    public function test_fixed_discount_capped_at_subtotal(): void
    {
        Coupon::create(['code' => 'BIG', 'type' => 'fixed', 'value' => 500, 'is_active' => true]);

        $res = $this->postJson('/api/coupons/validate', [
            'code' => 'BIG', 'subtotal' => 200,
        ]);

        $res->assertOk();
        $this->assertEquals(200, $res->json('discount'));
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->postJson('/api/coupons/validate', ['code' => 'NOPE', 'subtotal' => 100])
            ->assertNotFound();
    }

    public function test_inactive_coupon_rejected(): void
    {
        Coupon::create(['code' => 'OFF', 'type' => 'fixed', 'value' => 50, 'is_active' => false]);

        $this->postJson('/api/coupons/validate', ['code' => 'OFF', 'subtotal' => 100])
            ->assertStatus(422);
    }

    public function test_expired_coupon_rejected(): void
    {
        Coupon::create([
            'code' => 'EXP', 'type' => 'fixed', 'value' => 50,
            'is_active' => true, 'expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/coupons/validate', ['code' => 'EXP', 'subtotal' => 100])
            ->assertStatus(422);
    }

    public function test_max_uses_exhausted_rejected(): void
    {
        Coupon::create([
            'code' => 'USED', 'type' => 'fixed', 'value' => 50,
            'is_active' => true, 'max_uses' => 3, 'used_count' => 3,
        ]);

        $this->postJson('/api/coupons/validate', ['code' => 'USED', 'subtotal' => 100])
            ->assertStatus(422);
    }

    public function test_below_min_amount_rejected(): void
    {
        Coupon::create([
            'code' => 'MIN', 'type' => 'fixed', 'value' => 50,
            'is_active' => true, 'min_amount' => 1000,
        ]);

        $this->postJson('/api/coupons/validate', ['code' => 'MIN', 'subtotal' => 500])
            ->assertStatus(422);
    }
}
