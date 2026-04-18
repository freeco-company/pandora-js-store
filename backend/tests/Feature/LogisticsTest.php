<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\EcpayLogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LogisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.ecpay.merchant_id', '2000933');
        Config::set('services.ecpay.hash_key', 'XBERn1YOvpM9nfZc');
        Config::set('services.ecpay.hash_iv', 'h1ONHk4P4yqbl5LK');
        Config::set('services.ecpay.mode', 'sandbox');
        Config::set('services.ecpay.sender_name', '法芮可有限公司');
        Config::set('services.ecpay.sender_cellphone', '0920033849');
    }

    private function cvsOrder(array $override = []): Order
    {
        $customer = Customer::create([
            'name' => 'Tester', 'email' => 'test@x.com', 'phone' => '0912345678',
            'password' => bcrypt('x'),
        ]);
        $product = Product::create([
            'name' => 'Test SKU', 'slug' => 'test-sku',
            'price' => 1000, 'is_active' => true,
        ]);
        $order = Order::create(array_merge([
            'order_number' => 'PDTESTCVS1',
            'customer_id' => $customer->id,
            'status' => 'processing',
            'pricing_tier' => 'regular',
            'subtotal' => 1000, 'shipping_fee' => 0, 'total' => 1000,
            'payment_method' => 'ecpay_credit',
            'payment_status' => 'paid',
            'shipping_method' => 'cvs_711',
            'shipping_name' => '王小明',
            'shipping_phone' => '0987654321',
            'shipping_store_id' => '131386',
            'shipping_store_name' => '台北羅斯福店',
        ], $override));
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Test SKU',
            'quantity' => 2, 'unit_price' => 500, 'subtotal' => 1000,
        ]);
        return $order;
    }

    public function test_create_cvs_success_parses_and_stores_ids(): void
    {
        $order = $this->cvsOrder();

        Http::fake([
            'logistics-stage.ecpay.com.tw/*' => Http::response(
                http_build_query([
                    'MerchantID' => '2000933',
                    'MerchantTradeNo' => $order->order_number,
                    'RtnCode' => 1,
                    'RtnMsg' => '訂單處理中',
                    'AllPayLogisticsID' => '1234567890',
                    'LogisticsType' => 'CVS',
                    'LogisticsSubType' => 'UNIMARTC2C',
                    'GoodsAmount' => 1000,
                    'UpdateStatusDate' => '2026/04/17 22:00:00',
                    'ReceiverName' => '王小明',
                    'ReceiverCellPhone' => '0987654321',
                    'BookingNote' => 'Q993K01',
                    'CVSValidationNo' => '1234',
                    'CheckMacValue' => 'FAKE',
                ]),
                200,
            ),
        ]);

        $result = app(EcpayLogisticsService::class)->createCvsShipment($order);

        $this->assertSame(1, (int) $result['RtnCode']);
        $this->assertSame('1234567890', $result['AllPayLogisticsID']);

        $fresh = $order->fresh();
        $this->assertSame('1234567890', $fresh->ecpay_logistics_id);
        $this->assertSame('Q993K01', $fresh->booking_note);
        $this->assertSame('1234', $fresh->cvs_validation_no);
        $this->assertNotNull($fresh->logistics_created_at);
    }

    public function test_create_cvs_failure_stores_error_message_and_throws(): void
    {
        $order = $this->cvsOrder();

        Http::fake([
            'logistics-stage.ecpay.com.tw/*' => Http::response(
                http_build_query([
                    'MerchantID' => '2000933',
                    'MerchantTradeNo' => $order->order_number,
                    'RtnCode' => 10900001,
                    'RtnMsg' => 'ReceiverStoreID 不存在',
                    'CheckMacValue' => 'FAKE',
                ]),
                200,
            ),
        ]);

        $this->expectException(\RuntimeException::class);
        try {
            app(EcpayLogisticsService::class)->createCvsShipment($order);
        } finally {
            $fresh = $order->fresh();
            $this->assertNull($fresh->ecpay_logistics_id);
            $this->assertStringContainsString('10900001', (string) $fresh->logistics_status_msg);
        }
    }

    public function test_create_cvs_on_non_cvs_order_throws(): void
    {
        $order = $this->cvsOrder(['shipping_method' => 'home_delivery']);

        $this->expectException(\InvalidArgumentException::class);
        app(EcpayLogisticsService::class)->createCvsShipment($order);
    }

    public function test_create_cvs_twice_is_idempotent(): void
    {
        $order = $this->cvsOrder(['ecpay_logistics_id' => 'EXISTING123']);

        $result = app(EcpayLogisticsService::class)->createCvsShipment($order);
        $this->assertTrue($result['already']);
        $this->assertSame('EXISTING123', $result['logistics_id']);
    }

    public function test_create_cvs_missing_sender_cellphone_throws(): void
    {
        Config::set('services.ecpay.sender_cellphone', '');
        $order = $this->cvsOrder();

        $this->expectException(\RuntimeException::class);
        app(EcpayLogisticsService::class)->createCvsShipment($order);
    }

    public function test_status_callback_2030_marks_shipped(): void
    {
        $order = $this->cvsOrder(['ecpay_logistics_id' => '9999999999']);

        // Build a CheckMacValue that our verifyCallback will accept
        $data = [
            'MerchantID' => '2000933',
            'MerchantTradeNo' => $order->order_number,
            'AllPayLogisticsID' => '9999999999',
            'RtnCode' => 2030,
            'RtnMsg' => '商品已送達門市',
        ];
        $data['CheckMacValue'] = app(\App\Services\EcpayService::class)->generateCheckMac($data, 'md5');

        $res = $this->post('/api/logistics/ecpay/status', $data);
        $res->assertOk();
        $this->assertSame('1|OK', $res->content());
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_status_callback_2031_marks_completed(): void
    {
        $order = $this->cvsOrder(['ecpay_logistics_id' => '8888888888', 'status' => 'shipped']);

        $data = [
            'MerchantID' => '2000933',
            'MerchantTradeNo' => $order->order_number,
            'AllPayLogisticsID' => '8888888888',
            'RtnCode' => 2031,
            'RtnMsg' => '消費者已取件',
        ];
        $data['CheckMacValue'] = app(\App\Services\EcpayService::class)->generateCheckMac($data, 'md5');

        $this->post('/api/logistics/ecpay/status', $data)->assertOk();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_status_callback_2032_cod_marks_cod_no_pickup(): void
    {
        $order = $this->cvsOrder([
            'ecpay_logistics_id' => '7777777777',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);

        $data = [
            'MerchantID' => '2000933',
            'MerchantTradeNo' => $order->order_number,
            'AllPayLogisticsID' => '7777777777',
            'RtnCode' => 2032,
            'RtnMsg' => '商品已退回原門市',
        ];
        $data['CheckMacValue'] = app(\App\Services\EcpayService::class)->generateCheckMac($data, 'md5');

        $this->post('/api/logistics/ecpay/status', $data)->assertOk();
        $this->assertSame('cod_no_pickup', $order->fresh()->status);
    }

    public function test_status_callback_invalid_mac_rejected(): void
    {
        $order = $this->cvsOrder(['ecpay_logistics_id' => '1111111111']);

        $res = $this->post('/api/logistics/ecpay/status', [
            'MerchantID' => '2000933',
            'MerchantTradeNo' => $order->order_number,
            'AllPayLogisticsID' => '1111111111',
            'RtnCode' => 2030,
            'CheckMacValue' => 'BAD',
        ]);
        $res->assertOk();
        $this->assertStringContainsString('CheckMacValue Error', $res->content());
        $this->assertNotSame('shipped', $order->fresh()->status);
    }
}
