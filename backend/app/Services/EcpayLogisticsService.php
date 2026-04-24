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

    private string $apiBaseUrl;

    public function __construct(private EcpayService $payment)
    {
        $this->merchantId = (string) config('services.ecpay.merchant_id');
        $this->hashKey = (string) config('services.ecpay.hash_key');
        $this->hashIv = (string) config('services.ecpay.hash_iv');
        $this->apiBaseUrl = config('services.ecpay.mode') === 'production'
            ? 'https://logistics.ecpay.com.tw'
            : 'https://logistics-stage.ecpay.com.tw';
        $this->apiUrl = $this->apiBaseUrl . '/Express/Create';
        $this->senderName = (string) config('services.ecpay.sender_name', '法芮可有限公司');
        $this->senderCellPhone = (string) config('services.ecpay.sender_cellphone', '');
    }

    /**
     * Query ECPay /Helper/QueryLogisticsInfo for an order that ALREADY has
     * `ecpay_logistics_id` set, and backfill the remaining sub-fields
     * (BookingNote / CVSPaymentNo / CVSValidationNo / status_msg).
     *
     * Used after an admin manually pastes the AllPayLogisticsID from ECPay's
     * backoffice (for an order stuck in [300] because the async callback
     * was dropped due to a CheckMacValue mismatch before v2.7.4).
     *
     * @throws \RuntimeException on ECPay error or unexpected response format.
     */
    public function queryByLogisticsId(Order $order): array
    {
        if (empty($order->ecpay_logistics_id)) {
            throw new \RuntimeException('此訂單沒有物流單號，無法查詢');
        }

        $params = [
            'MerchantID' => $this->merchantId,
            'AllPayLogisticsID' => (string) $order->ecpay_logistics_id,
            'TimeStamp' => (string) time(),
        ];
        $params['CheckMacValue'] = $this->payment->generateCheckMac($params, 'md5');

        Log::info('ECPay QueryLogisticsInfo request', [
            'order' => $order->order_number,
            'logistics_id' => $order->ecpay_logistics_id,
        ]);

        $response = Http::asForm()->post($this->apiBaseUrl . '/Helper/QueryLogisticsInfo', $params);
        $body = (string) $response->body();

        Log::info('ECPay QueryLogisticsInfo response', [
            'order' => $order->order_number,
            'body' => mb_strimwidth($body, 0, 500, '…'),
        ]);

        // ECPay error responses use `0|<msg>` instead of key=value pairs.
        if (str_starts_with($body, '0|')) {
            throw new \RuntimeException('綠界回應：' . substr($body, 2));
        }

        parse_str($body, $data);
        if (empty($data) || ! is_array($data)) {
            throw new \RuntimeException('綠界回應無法解析：' . mb_strimwidth($body, 0, 200, '…'));
        }

        $updates = [];
        $rtnCode = $data['RtnCode'] ?? null;
        $rtnMsg = (string) ($data['RtnMsg'] ?? '');
        if ($rtnCode !== null) {
            $updates['logistics_status_msg'] = "[{$rtnCode}] {$rtnMsg}（查詢回填）";
        }
        foreach (['BookingNote' => 'booking_note', 'CVSPaymentNo' => 'cvs_payment_no', 'CVSValidationNo' => 'cvs_validation_no'] as $src => $dst) {
            if (! empty($data[$src])) {
                $updates[$dst] = (string) $data[$src];
            }
        }
        if (! empty($updates)) {
            $order->update($updates);
        }

        return $data;
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

        // If we already sent this MerchantTradeNo to ECPay (rtn_code 300 pending
        // waiting for callback), don't resend — ECPay will reject with
        // 「廠商訂單編號重覆」. Admin must use「清除物流」先.
        if ($order->logistics_created_at && str_starts_with((string) $order->logistics_status_msg, '[300]')) {
            throw new \RuntimeException('此訂單已送至綠界且處理中（rtn_code 300），請等待綠界 callback；若要重送請先點「清除物流」。');
        }

        // Credit-card orders: shipment must wait until payment is confirmed.
        // COD can ship pre-payment (cash at pickup).
        if ($order->payment_method !== 'cod' && $order->payment_status !== 'paid') {
            throw new \RuntimeException('信用卡訂單尚未付款成功，請等付款完成後再建立物流。');
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

        // Logistics API uses MD5, NOT SHA256 (payment's default).
        $params['CheckMacValue'] = $this->payment->generateCheckMac($params, 'md5');

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

        // rtn_code 300 「訂單處理中(綠界已收到訂單資料)」— NOT a failure.
        // Green World已收下單，最終結果會透過 ServerReplyURL callback 回來填
        // AllPayLogisticsID。此時若重送會吃到「廠商訂單編號重覆」。
        // 我們標記為 pending、寫 logistics_created_at 擋後續重試，並提早返回。
        if ($rtnCode === 300) {
            $order->update([
                'logistics_status_msg' => "[300] {$rtnMsg}（等待綠界回傳物流編號）",
                'logistics_created_at' => now(),
            ]);
            Log::info('ECPay logistics pending (rtn_code=300)', [
                'order' => $order->order_number,
                'rtn_msg' => $rtnMsg,
            ]);
            return ['pending' => true, 'rtn_code' => 300, 'rtn_msg' => $rtnMsg];
        }

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

    /**
     * "商品A x2#商品B x1" style for GoodsName.
     *
     * ECPay GoodsName limit: 50 char-weights where Chinese counts as 2,
     * English/digits as 1. Easiest safe bound: truncate at 25 Unicode chars
     * (25 Chinese = 50 weight; 25 English = 25 weight, both fit).
     */
    private function formatGoodsName(Order $order): string
    {
        $order->loadMissing('items');
        $parts = $order->items->map(fn ($it) => "{$it->product_name} x{$it->quantity}")->all();
        $name = implode('#', $parts);
        if (mb_strlen($name) > 25) {
            $name = mb_substr($name, 0, 22) . '...';
        }
        return $name;
    }

    /** Safely truncate a name to max N CJK/mixed chars. */
    private function truncateCjk(string $s, int $max): string
    {
        $s = trim($s);
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
    }
}
