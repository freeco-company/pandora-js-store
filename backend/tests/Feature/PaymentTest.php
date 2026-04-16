<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Services\EcpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.ecpay.merchant_id', '3002607');
        Config::set('services.ecpay.hash_key', 'pwFHCqoQZGmho4w6');
        Config::set('services.ecpay.hash_iv', 'EkRm7iFT261dpevs');
        Config::set('services.ecpay.mode', 'sandbox');
    }

    private function order(array $override = []): Order
    {
        $customer = Customer::create([
            'name' => 'Tester', 'email' => 't@e.com',
            'phone' => '000', 'password' => bcrypt('x'),
        ]);

        $product = \App\Models\Product::create([
            'name' => 'Test Product', 'slug' => 'test-product',
            'price' => 1000, 'is_active' => true,
        ]);

        $order = Order::create(array_merge([
            'order_number' => 'PDTEST001',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'shipping_fee' => 0,
            'total' => 1000,
            'payment_method' => 'ecpay_credit',
            'payment_status' => 'unpaid',
            'shipping_method' => 'home_delivery',
        ], $override));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Test Product',
            'quantity' => 1, 'unit_price' => 1000, 'subtotal' => 1000,
        ]);

        return $order;
    }

    public function test_create_payment_returns_action_and_params(): void
    {
        $order = $this->order();

        $res = $this->postJson('/api/payment/create', [
            'order_number' => $order->order_number,
        ]);

        $res->assertOk()
            ->assertJsonStructure(['action', 'params' => ['MerchantID', 'MerchantTradeNo', 'TotalAmount', 'CheckMacValue']])
            ->assertJsonPath('params.MerchantTradeNo', $order->order_number)
            ->assertJsonPath('params.TotalAmount', 1000);
    }

    public function test_create_payment_rejects_already_paid_order(): void
    {
        $order = $this->order(['payment_status' => 'paid']);

        $this->postJson('/api/payment/create', ['order_number' => $order->order_number])
            ->assertStatus(400);
    }

    public function test_create_payment_validates_order_exists(): void
    {
        $this->postJson('/api/payment/create', ['order_number' => 'GHOST'])
            ->assertStatus(422);
    }

    public function test_callback_with_valid_mac_marks_order_paid(): void
    {
        $order = $this->order();

        $data = [
            'MerchantID' => '3002607',
            'MerchantTradeNo' => $order->order_number,
            'RtnCode' => '1',
            'RtnMsg' => 'Succeeded',
            'TradeNo' => 'TRADE123',
            'TradeAmt' => '1000',
            'PaymentDate' => '2026/04/15 10:00:00',
            'PaymentType' => 'Credit_CreditCard',
        ];

        $data['CheckMacValue'] = app(EcpayService::class)->generateCheckMac($data);

        $res = $this->post('/api/payment/ecpay/callback', $data);

        $res->assertOk();
        $this->assertSame('1|OK', $res->content());
        $this->assertDatabaseHas('orders', [
            'order_number' => $order->order_number,
            'payment_status' => 'paid',
            'status' => 'processing',
            'ecpay_trade_no' => 'TRADE123',
        ]);
    }

    public function test_callback_with_invalid_mac_rejected(): void
    {
        $order = $this->order();

        $res = $this->post('/api/payment/ecpay/callback', [
            'MerchantID' => '3002607',
            'MerchantTradeNo' => $order->order_number,
            'RtnCode' => '1',
            'TradeNo' => 'X',
            'CheckMacValue' => 'INVALID_HASH',
        ]);

        $res->assertOk();
        $this->assertStringContainsString('CheckMacValue Error', $res->content());
        $this->assertDatabaseHas('orders', [
            'order_number' => $order->order_number,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_callback_failure_marks_order_failed(): void
    {
        $order = $this->order();

        $data = [
            'MerchantID' => '3002607',
            'MerchantTradeNo' => $order->order_number,
            'RtnCode' => '10100073',
            'RtnMsg' => 'Card rejected',
            'TradeNo' => 'FAIL1',
            'TradeAmt' => '1000',
        ];

        $data['CheckMacValue'] = app(EcpayService::class)->generateCheckMac($data);

        $this->post('/api/payment/ecpay/callback', $data)->assertOk();

        $this->assertDatabaseHas('orders', [
            'order_number' => $order->order_number,
            'payment_status' => 'failed',
        ]);
    }
}
