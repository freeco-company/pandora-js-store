<?php

namespace Tests\Feature;

use App\Models\Blacklist;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodBlacklistTest extends TestCase
{
    use RefreshDatabase;

    private function order(Customer $c, string $payment = 'cod', string $status = 'pending'): Order
    {
        return Order::create([
            'order_number'    => 'TEST' . uniqid(),
            'customer_id'     => $c->id,
            'status'          => $status,
            'pricing_tier'    => 'regular',
            'subtotal'        => 1000,
            'shipping_fee'    => 0,
            'discount'        => 0,
            'total'           => 1000,
            'payment_method'  => $payment,
            'payment_status'  => 'unpaid',
            'shipping_method' => 'cvs_711',
            'shipping_name'   => $c->name,
            'shipping_phone'  => $c->phone,
        ]);
    }

    public function test_order_status_cod_no_pickup_auto_blacklists_customer(): void
    {
        $c = Customer::create([
            'name' => 'A', 'email' => 'a@e.com', 'phone' => '0987000111',
            'password' => bcrypt('x'),
        ]);
        $o = $this->order($c, 'cod', 'processing');

        $this->assertFalse(Blacklist::isBlacklisted($c->email, $c->phone));

        $o->update(['status' => 'cod_no_pickup']);

        $this->assertTrue(Blacklist::isBlacklisted($c->email, $c->phone));
        $bl = Blacklist::where('email', $c->email)->first();
        $this->assertStringContainsString('未取件', $bl->reason);
        $this->assertTrue($bl->is_active);
    }

    public function test_non_cod_order_no_pickup_does_not_blacklist(): void
    {
        $c = Customer::create([
            'name' => 'B', 'email' => 'b@e.com', 'phone' => '0987000222',
            'password' => bcrypt('x'),
        ]);
        $o = $this->order($c, 'bank_transfer', 'processing');

        $o->update(['status' => 'cod_no_pickup']);

        $this->assertFalse(Blacklist::isBlacklisted($c->email, $c->phone));
    }

    public function test_cancelled_cod_order_does_not_blacklist(): void
    {
        $c = Customer::create([
            'name' => 'C', 'email' => 'c@e.com', 'phone' => '0987000333',
            'password' => bcrypt('x'),
        ]);
        $o = $this->order($c, 'cod', 'processing');

        $o->update(['status' => 'cancelled']);

        $this->assertFalse(Blacklist::isBlacklisted($c->email, $c->phone));
    }

    public function test_blacklist_is_idempotent(): void
    {
        $c = Customer::create([
            'name' => 'D', 'email' => 'd@e.com', 'phone' => '0987000444',
            'password' => bcrypt('x'),
        ]);
        $o1 = $this->order($c, 'cod', 'processing');
        $o1->update(['status' => 'cod_no_pickup']);

        $o2 = $this->order($c, 'cod', 'processing');
        $o2->update(['status' => 'cod_no_pickup']);

        $this->assertSame(1, Blacklist::where('email', $c->email)->count());
    }

    public function test_checkout_rejects_cod_order_from_blacklisted_customer(): void
    {
        Blacklist::create([
            'email' => 'banned@e.com', 'phone' => null,
            'reason' => '過往未取件', 'is_active' => true,
        ]);
        $product = \App\Models\Product::create([
            'name' => 'X', 'slug' => 'x', 'price' => 500,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer' => ['name' => 'Banned', 'email' => 'banned@e.com', 'phone' => '0912345678'],
            'payment_method' => 'cod',
            'shipping_method' => 'cvs_711',
            'shipping_name' => 'Banned', 'shipping_phone' => '0912345678',
            'shipping_store_id' => 'TEST001', 'shipping_store_name' => '測試門市',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('貨到付款', $res->json('message') ?? '');
    }
}
