<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EcpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private EcpayService $ecpayService,
        private OrderController $orderController,
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
            // Only run celebrations on the FIRST transition to paid — the
            // callback can fire multiple times (ECPay retries until 1|OK).
            $wasUnpaid = $order->payment_status !== 'paid';

            $order->update([
                'payment_status' => 'paid',
                'ecpay_trade_no' => $data['TradeNo'],
                'status' => 'processing',
            ]);

            if ($wasUnpaid) {
                $fresh = $order->fresh();
                try {
                    $this->orderController->runCelebrations($fresh);
                } catch (\Throwable $e) {
                    Log::error('Failed to run celebrations after ECPay callback', [
                        'order_number' => $order->order_number,
                        'error' => $e->getMessage(),
                    ]);
                }

                // For CVS orders, book the shipment now that payment cleared.
                // Gated by ECPAY_LOGISTICS_AUTO; always also reachable via the
                // "重新建立物流" admin row action.
                if (
                    config('services.ecpay.logistics_auto')
                    && in_array($fresh->shipping_method, ['cvs_711', 'cvs_family'])
                ) {
                    $this->orderController->tryCreateLogistics($fresh);
                }
            }
        } else {
            $order->update([
                'payment_status' => 'failed',
            ]);
        }

        return '1|OK';
    }
}
