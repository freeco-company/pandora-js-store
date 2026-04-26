<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Services\LineMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * COD 取件率改善 — 訂單在客人於 LINE 點「確認出貨」前停在 pending_confirmation。
 *
 * 兩段流程：
 *   1. bind-line：客人在 order-complete 點 CTA → LINE Login OAuth
 *      → callback 拿到 line userId → bindLineAndPush() 寫進訂單並推 Flex 訊息
 *   2. confirm：客人點 Flex 上的「確認出貨」按鈕 → LineWebhookController
 *      接到 postback → confirm() 把訂單轉 processing + 觸發後續流程
 */
class OrderConfirmationController extends Controller
{
    public function __construct(
        private LineMessagingService $line,
        private OrderController $orderController,
    ) {}

    /**
     * Called from AuthController after LINE Login OAuth callback when state
     * carries the bind-order intent. Verifies the order's confirmation_token,
     * stores the line_user_id, and pushes the Flex confirmation message.
     */
    public function bindLineAndPush(string $orderNumber, string $token, string $lineUserId, string $name = '', ?string $email = null): bool
    {
        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order) {
            Log::warning('[cod-confirm] bind: order not found', ['order' => $orderNumber]);
            return false;
        }

        // 既然客人剛走完 LINE OAuth，順便把 customer.line_id 也同步起來
        // （之後 abandoned-cart / pickup-reminder 等 push 才有 userId 可推）
        if ($order->customer_id) {
            $customer = Customer::find($order->customer_id);
            if ($customer && empty($customer->line_id)) {
                $customer->line_id = $lineUserId;
                if ($email && str_ends_with($customer->email ?? '', '@line.user')) {
                    $customer->email = $email;
                }
                $customer->save();
            }
        }

        if ($order->status !== 'pending_confirmation') {
            // 已確認 / 已取消都當「綁定成功」回，避免前端誤判
            return true;
        }

        if (!$order->confirmation_token || !hash_equals($order->confirmation_token, $token)) {
            Log::warning('[cod-confirm] bind: token mismatch', ['order' => $orderNumber]);
            return false;
        }

        $order->line_user_id = $lineUserId;
        $order->save();

        // 推 Flex 確認訊息（內含「確認出貨」postback 按鈕）
        $flex = LineMessagingService::codConfirmationFlex(
            orderNumber: $order->order_number,
            token: $order->confirmation_token,
            total: (int) $order->total,
        );
        $altText = "請確認您的貨到付款訂單 {$order->order_number}";
        $pushed = $this->line->pushFlex($lineUserId, $altText, $flex);
        if (!$pushed) {
            Log::warning('[cod-confirm] flex push failed', ['order' => $orderNumber]);
        }
        return true;
    }

    /**
     * Called by LineWebhookController on receiving the "confirm_cod" postback.
     * Idempotent — repeated calls on an already-confirmed order are safe.
     *
     * @return array{ok: bool, message: string}
     */
    public function confirm(string $orderNumber, string $token, string $lineUserId): array
    {
        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order) {
            return ['ok' => false, 'message' => '查無此訂單'];
        }

        if ($order->status === 'cancelled') {
            return ['ok' => false, 'message' => "訂單 {$orderNumber} 已取消，無法確認。如仍需購買請重新下單。"];
        }

        // 已確認 — idempotent, 直接回成功
        if ($order->status !== 'pending_confirmation') {
            return ['ok' => true, 'message' => "訂單 {$orderNumber} 已確認，3 個工作天內出貨 🚚"];
        }

        if (!$order->confirmation_token || !hash_equals($order->confirmation_token, $token)) {
            Log::warning('[cod-confirm] confirm: token mismatch', ['order' => $orderNumber]);
            return ['ok' => false, 'message' => '確認連結已失效，請聯繫客服'];
        }

        if ($order->line_user_id && $order->line_user_id !== $lineUserId) {
            Log::warning('[cod-confirm] confirm: line_user_id mismatch', [
                'order' => $orderNumber,
                'expected_prefix' => substr((string) $order->line_user_id, 0, 6),
                'got_prefix' => substr($lineUserId, 0, 6),
            ]);
            return ['ok' => false, 'message' => '請使用下單時加入的 LINE 帳號確認'];
        }

        $order->update([
            'status' => 'processing',
            'confirmed_at' => now(),
        ]);

        // 確認後才跑 celebrations + 建物流（與 OrderController::store 對應分支對齊）
        try {
            $this->orderController->runCelebrations($order->fresh());
        } catch (\Throwable $e) {
            Log::error('[cod-confirm] celebrations failed', ['order' => $orderNumber, 'msg' => $e->getMessage()]);
        }

        if (
            config('services.ecpay.logistics_auto')
            && in_array($order->shipping_method, ['cvs_711', 'cvs_family'])
        ) {
            $this->orderController->tryCreateLogistics($order->fresh());
        }

        return ['ok' => true, 'message' => "訂單 {$orderNumber} 已確認，3 個工作天內出貨 🚚 請保持手機暢通，超商到貨會通知您。"];
    }

    /**
     * GET /api/orders/{orderNumber}/confirmation-status
     * Frontend polls this on order-complete to show "已確認" once the
     * webhook callback flips the status. Auth via either:
     *   - ?token=...  (the per-order confirmation_token, only known to the
     *     buyer who just placed the order — used during the LINE-bind flow)
     *   - ?email=...  (matches customer email — used for non-COD orders)
     */
    public function status(string $orderNumber, \Illuminate\Http\Request $request): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with('customer:id,email')
            ->first();
        if (!$order) {
            return response()->json(['message' => '查無此訂單'], 404);
        }

        $token = (string) $request->query('token', '');
        $email = (string) $request->query('email', '');

        $tokenOk = $token !== '' && $order->confirmation_token
            && hash_equals($order->confirmation_token, $token);
        $emailOk = $email !== '' && $order->customer
            && strtolower(trim($email)) === strtolower((string) $order->customer->email);

        if (!$tokenOk && !$emailOk) {
            return response()->json(['message' => '需提供 token 或 email 驗證身分'], 403);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'shipping_method' => $order->shipping_method,
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'line_bound' => !empty($order->line_user_id),
            'needs_line_confirmation' => $order->status === 'pending_confirmation',
        ]);
    }
}
