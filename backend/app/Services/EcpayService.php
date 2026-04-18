<?php

namespace App\Services;

use App\Models\Order;

class EcpayService
{
    private string $merchantId;
    private string $hashKey;
    private string $hashIv;
    private string $apiUrl;

    public function __construct()
    {
        // Cast to string so tests / CI environments without ECPay env vars
        // can still boot the service (e.g. any test that touches
        // OrderController which injects EcpayLogisticsService → EcpayService).
        // Tests that actually hit the service set real values via Config::set.
        $this->merchantId = (string) config('services.ecpay.merchant_id');
        $this->hashKey = (string) config('services.ecpay.hash_key');
        $this->hashIv = (string) config('services.ecpay.hash_iv');
        $this->apiUrl = config('services.ecpay.mode') === 'production'
            ? 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5'
            : 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';
    }

    public function createPayment(Order $order): array
    {
        // IMPORTANT: pass RAW values here — generateCheckMac() urlencodes
        // the whole string once. Pre-encoding TradeDesc would double-encode
        // it for the hash while the form posts the single-encoded form,
        // which trips ECPay's 10200073 "CheckMacValue Error".

        // ECPay requires unique MerchantTradeNo per request. On retry payment,
        // append a short suffix to avoid "訂單編號重覆" (10300028) error.
        // Max 20 chars: order_number is ~16, suffix is 2 chars (R1–R9, RA–RZ).
        $tradeNo = $order->order_number;
        $retryCount = (int) $order->payment_retry_count;
        if ($retryCount > 0) {
            $suffix = 'R' . ($retryCount <= 9 ? $retryCount : chr(55 + $retryCount)); // R1..R9, RA..RZ
            $tradeNo = substr($order->order_number, 0, 20 - strlen($suffix)) . $suffix;
        }
        $order->increment('payment_retry_count');

        $params = [
            'MerchantID' => $this->merchantId,
            'MerchantTradeNo' => $tradeNo,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => (int) $order->total,
            'TradeDesc' => '婕樂纖仙女館訂單',
            'ItemName' => $this->formatItemName($order),
            'ReturnURL' => config('app.url') . '/api/payment/ecpay/callback',
            // Front-end "thank-you" page after ECPay redirects the browser
            // back. Keep this aligned with the existing /order-complete route
            // — there is no separate /checkout/result page.
            'OrderResultURL' => config('services.ecpay.frontend_url') . '/order-complete?order=' . $order->order_number,
            'ChoosePayment' => 'ALL',
            'EncryptType' => 1,
        ];

        $params['CheckMacValue'] = $this->generateCheckMac($params);

        return [
            'action' => $this->apiUrl,
            'params' => $params,
        ];
    }

    public function verifyCallback(array $data, string $algo = 'sha256'): bool
    {
        $checkMac = $data['CheckMacValue'] ?? '';
        unset($data['CheckMacValue']);

        return strtoupper($this->generateCheckMac($data, $algo)) === strtoupper($checkMac);
    }

    /**
     * Payment (AioCheckOut V5) uses sha256 with EncryptType=1.
     * Logistics (Express/Create, Map/Status callbacks) uses md5 — no EncryptType.
     * The encoding/sorting steps are identical; only the hash algorithm differs.
     */
    public function generateCheckMac(array $params, string $algo = 'sha256'): string
    {
        ksort($params, SORT_NATURAL | SORT_FLAG_CASE);

        $checkStr = 'HashKey=' . $this->hashKey;
        foreach ($params as $key => $value) {
            $checkStr .= "&{$key}={$value}";
        }
        $checkStr .= '&HashIV=' . $this->hashIv;

        // ECPay follows .NET's HttpUtility.UrlEncode, which leaves a few
        // characters unencoded that PHP's urlencode() escapes. Revert those
        // so our signature matches what ECPay's server computes.
        $checkStr = urlencode($checkStr);
        $checkStr = strtolower($checkStr);
        $checkStr = strtr($checkStr, [
            '%2d' => '-',
            '%5f' => '_',
            '%2e' => '.',
            '%21' => '!',
            '%2a' => '*',
            '%28' => '(',
            '%29' => ')',
        ]);

        return strtoupper(hash($algo, $checkStr));
    }

    private function formatItemName(Order $order): string
    {
        return $order->items->map(function ($item) {
            return "{$item->product_name} x{$item->quantity}";
        })->implode('#');
    }
}
