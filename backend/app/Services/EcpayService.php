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
        $this->merchantId = config('services.ecpay.merchant_id');
        $this->hashKey = config('services.ecpay.hash_key');
        $this->hashIv = config('services.ecpay.hash_iv');
        $this->apiUrl = config('services.ecpay.mode') === 'production'
            ? 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5'
            : 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';
    }

    public function createPayment(Order $order): array
    {
        $params = [
            'MerchantID' => $this->merchantId,
            'MerchantTradeNo' => $order->order_number,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => (int) $order->total,
            'TradeDesc' => urlencode('婕樂纖仙女館訂單'),
            'ItemName' => $this->formatItemName($order),
            'ReturnURL' => config('app.url') . '/api/payment/ecpay/callback',
            'OrderResultURL' => config('services.ecpay.frontend_url') . '/checkout/result?order=' . $order->order_number,
            'ChoosePayment' => 'ALL',
            'EncryptType' => 1,
        ];

        $params['CheckMacValue'] = $this->generateCheckMac($params);

        return [
            'action' => $this->apiUrl,
            'params' => $params,
        ];
    }

    public function verifyCallback(array $data): bool
    {
        $checkMac = $data['CheckMacValue'] ?? '';
        unset($data['CheckMacValue']);

        return strtoupper($this->generateCheckMac($data)) === strtoupper($checkMac);
    }

    public function generateCheckMac(array $params): string
    {
        ksort($params);

        $checkStr = 'HashKey=' . $this->hashKey;
        foreach ($params as $key => $value) {
            $checkStr .= "&{$key}={$value}";
        }
        $checkStr .= '&HashIV=' . $this->hashIv;

        $checkStr = urlencode($checkStr);
        $checkStr = strtolower($checkStr);

        return strtoupper(hash('sha256', $checkStr));
    }

    private function formatItemName(Order $order): string
    {
        return $order->items->map(function ($item) {
            return "{$item->product_name} x{$item->quantity}";
        })->implode('#');
    }
}
