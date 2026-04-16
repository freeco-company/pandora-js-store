<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Create CVS (超商取貨) shipments against ECPay's Express/Create API,
 * then write the returned AllPayLogisticsID / BookingNote / CVSPaymentNo
 * back onto the Order row.
 *
 * The MerchantID / HashKey / HashIV are shared with the payment
 * (AioCheckOut) account — ECPay issues one credential set per merchant
 * that covers payment + logistics + invoicing.
 *
 * Sender info comes from services.ecpay.sender_name /
 * services.ecpay.sender_cellphone (required on every CVS create call).
 */
class EcpayLogisticsService
{
    private string $merchantId;
    private string $hashKey;
    private string $hashIv;
    private string $apiUrl;
    private string $senderName;
    private string $senderCellPhone;

    public function __construct(private EcpayService $payment)
    {
        $this->merchantId = (string) config('services.ecpay.merchant_id');
        $this->hashKey = (string) config('services.ecpay.hash_key');
        $this->hashIv = (string) config('services.ecpay.hash_iv');
        $this->apiUrl = config('services.ecpay.mode') === 'production'
            ? 'https://logistics.ecpay.com.tw/Express/Create'
            : 'https://logistics-stage.ecpay.com.tw/Express/Create';
        $this->senderName = (string) config('services.ecpay.sender_name', '法芮可有限公司');
        $this->senderCellPhone = (string) config('services.ecpay.sender_cellphone', '');
    }

    /**
     * Create a CVS shipment for the given order.
     *
     * Returns the parsed ECPay response on success; throws on failure.
     * Idempotent when called twice — skips if `ecpay_logistics_id` is
     * already set (ECPay rejects duplicate MerchantTradeNo anyway).
     */
    public function createCvsShipment(Order $order): array
    {
        if ($order->ecpay_logistics_id) {
            return ['already' => true, 'logistics_id' => $order->ecpay_logistics_id];
        }

        if (! in_array($order->shipping_method, ['cvs_711', 'cvs_family'])) {
            throw new \InvalidArgumentException('createCvsShipment called on non-CVS order');
        }

        if (! $order->shipping_store_id) {
            throw new \InvalidArgumentException('Order has no shipping_store_id');
        }

        if (! $this->senderCellPhone) {
            throw new \RuntimeException('services.ecpay.sender_cellphone not configured');
        }

        $subType = match ($order->shipping_method) {
            'cvs_711' => 'UNIMARTC2C',
            'cvs_family' => 'FAMIC2C',
        };

        $isCod = $order->payment_method === 'cod';
        $total = (int) $order->total;

        $params = [
            'MerchantID' => $this->merchantId,
            'MerchantTradeNo' => $order->order_number,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'LogisticsType' => 'CVS',
            'LogisticsSubType' => $subType,
            'GoodsAmount' => $total,
            'CollectionAmount' => $isCod ? $total : 0,
            'IsCollection' => $isCod ? 'Y' : 'N',
            'GoodsName' => $this->formatGoodsName($order),
            'SenderName' => $this->truncateCjk($this->senderName, 10),
            'SenderCellPhone' => preg_replace('/\D/', '', $this->senderCellPhone),
            'ReceiverName' => $this->truncateCjk($order->shipping_name ?: '會員', 10),
            'ReceiverCellPhone' => preg_replace('/\D/', '', (string) $order->shipping_phone),
            'ReceiverEmail' => (string) ($order->customer?->email ?? ''),
            'TradeDesc' => '婕樂纖仙女館訂單',
            'ServerReplyURL' => config('app.url') . '/api/logistics/ecpay/reply',
            'LogisticsC2CReplyURL' => config('app.url') . '/api/logistics/ecpay/status',
            'Remark' => '',
            'PlatformID' => '',
            'ReceiverStoreID' => $order->shipping_store_id,
            'ReturnStoreID' => '',
        ];

        $params['CheckMacValue'] = $this->payment->generateCheckMac($params);

        Log::info('ECPay logistics create request', [
            'order' => $order->order_number,
            'sub_type' => $subType,
            'is_cod' => $isCod,
            'total' => $total,
        ]);

        $response = Http::asForm()
            ->timeout(15)
            ->post($this->apiUrl, $params);

        // ECPay returns key=value&key=value form-urlencoded body
        parse_str($response->body(), $parsed);

        if (! is_array($parsed) || ! isset($parsed['RtnCode'])) {
            Log::error('ECPay logistics response unparseable', [
                'order' => $order->order_number,
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('綠界回傳格式錯誤：' . \Illuminate\Support\Str::limit($response->body(), 200));
        }

        $rtnCode = (int) ($parsed['RtnCode'] ?? 0);
        $rtnMsg = (string) ($parsed['RtnMsg'] ?? '');

        if ($rtnCode !== 1) {
            $order->update(['logistics_status_msg' => "[{$rtnCode}] {$rtnMsg}"]);
            Log::error('ECPay logistics create failed', [
                'order' => $order->order_number,
                'rtn_code' => $rtnCode,
                'rtn_msg' => $rtnMsg,
            ]);
            throw new \RuntimeException("綠界建立物流失敗：[{$rtnCode}] {$rtnMsg}");
        }

        $order->update([
            'ecpay_logistics_id' => $parsed['AllPayLogisticsID'] ?? null,
            'cvs_payment_no' => $parsed['CVSPaymentNo'] ?? null,
            'cvs_validation_no' => $parsed['CVSValidationNo'] ?? null,
            'booking_note' => $parsed['BookingNote'] ?? null,
            'logistics_status_msg' => "[1] {$rtnMsg}",
            'logistics_created_at' => now(),
        ]);

        Log::info('ECPay logistics created', [
            'order' => $order->order_number,
            'logistics_id' => $parsed['AllPayLogisticsID'] ?? null,
            'booking_note' => $parsed['BookingNote'] ?? null,
        ]);

        return $parsed;
    }

    /** "商品A x2#商品B x1" style for GoodsName, truncated to 60 chars. */
    private function formatGoodsName(Order $order): string
    {
        $order->loadMissing('items');
        $parts = $order->items->map(fn ($it) => "{$it->product_name} x{$it->quantity}")->all();
        $name = implode('#', $parts);
        return mb_substr($name, 0, 60);
    }

    /** Safely truncate a name to max N CJK/mixed chars. */
    private function truncateCjk(string $s, int $max): string
    {
        $s = trim($s);
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }
}
