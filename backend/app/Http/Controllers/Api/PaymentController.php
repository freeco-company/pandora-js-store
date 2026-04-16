<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EcpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private EcpayService $ecpayService
    ) {}

    /**
     * Create ECPay payment form data for frontend to submit.
     */
    public function createPayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => 'required|string|exists:orders,order_number',
        ]);

        $order = Order::where('order_number', $request->order_number)
            ->with('items')
            ->firstOrFail();

        if ($order->payment_status === 'paid') {
            return response()->json(['error' => '此訂單已付款'], 400);
        }

        $paymentData = $this->ecpayService->createPayment($order);

        return response()->json($paymentData);
    }

    /**
     * ECPay payment callback (server-to-server).
     */
    public function ecpayCallback(Request $request): string
    {
        $data = $request->all();

        if (!$this->ecpayService->verifyCallback($data)) {
            return '0|CheckMacValue Error';
        }

        $order = Order::where('order_number', $data['MerchantTradeNo'])->first();

        if (!$order) {
            return '0|Order Not Found';
        }

        if ($data['RtnCode'] == '1') {
            $order->update([
                'payment_status' => 'paid',
                'ecpay_trade_no' => $data['TradeNo'],
                'status' => 'processing',
            ]);
        } else {
            $order->update([
                'payment_status' => 'failed',
            ]);
        }

        return '1|OK';
    }
}
