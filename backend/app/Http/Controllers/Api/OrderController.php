<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Models\Blacklist;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartPricingService;
use App\Services\OrderAchievementEvaluator;
use App\Services\OutfitService;
use App\Services\SerendipityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private CartPricingService $pricingService,
        private OrderAchievementEvaluator $evaluator,
        private OutfitService $outfitService,
        private SerendipityService $serendipity,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer.name' => 'required|string',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string',
            'payment_method' => 'required|in:ecpay_credit,cod,bank_transfer',
            'shipping_method' => 'required|in:cvs_711,cvs_family,home_delivery',
            'shipping_name' => 'required|string',
            'shipping_phone' => 'required|string',
            'coupon_code' => 'nullable|string',
        ]);

        // Block COD for blacklisted users
        if ($request->payment_method === 'cod') {
            $email = $request->input('customer.email');
            $phone = $request->input('customer.phone');
            if (Blacklist::isBlacklisted($email, $phone)) {
                return response()->json([
                    'message' => '由於過去有未取貨紀錄，您目前無法使用貨到付款，請選擇其他付款方式。',
                ], 422);
            }
        }

        // Re-calculate pricing server-side
        $pricing = $this->pricingService->calculate($request->items);

        // Validate and apply coupon if provided
        $coupon = null;
        $discount = 0;
        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();

            if (!$coupon || !$coupon->isValid()) {
                return response()->json(['message' => '優惠碼無效或已過期。'], 422);
            }

            if ($coupon->min_amount && $pricing['total'] < $coupon->min_amount) {
                return response()->json([
                    'message' => "訂單金額需滿 NT\${$coupon->min_amount} 才能使用此優惠碼。",
                ], 422);
            }

            $discount = match ($coupon->type) {
                'fixed' => min($coupon->value, $pricing['total']),
                'percentage' => round($pricing['total'] * ($coupon->value / 100), 0),
                default => 0,
            };
        }

        // Find or create customer
        $customer = Customer::firstOrCreate(
            ['email' => $request->input('customer.email')],
            [
                'name' => $request->input('customer.name'),
                'phone' => $request->input('customer.phone'),
                'password' => bcrypt(Str::random(16)),
            ]
        );

        $total = $pricing['total'] - $discount;

        $order = Order::create([
            'order_number' => 'PD' . now()->format('ymd') . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'coupon_id' => $coupon?->id,
            'status' => 'pending',
            'pricing_tier' => $pricing['tier'],
            'subtotal' => $pricing['total'],
            'shipping_fee' => 0, // Free shipping
            'discount' => $discount,
            'total' => $total,
            'payment_method' => $request->payment_method,
            'payment_status' => 'unpaid',
            'shipping_method' => $request->shipping_method,
            'shipping_name' => $request->shipping_name,
            'shipping_phone' => $request->shipping_phone,
            'shipping_address' => $request->shipping_address,
            'shipping_store_id' => $request->shipping_store_id,
            'shipping_store_name' => $request->shipping_store_name,
            'note' => $request->note,
        ]);

        // Increment coupon usage
        if ($coupon) {
            $coupon->increment('used_count');
        }

        foreach ($pricing['items'] as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
                'created_at' => now(),
            ]);
        }

        $order->load('items');

        // Gamification: award achievements, check outfit unlocks, maybe serendipity
        $awardedCodes = $this->evaluator->evaluate($customer->fresh(), $order, $coupon !== null);
        $newOutfits = $this->outfitService->checkUnlocks($customer->fresh());
        $serendipity = $this->serendipity->maybeGenerate($customer->fresh());

        // Send order confirmation email
        try {
            Mail::to($request->input('customer.email'))->send(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            Log::error('Failed to send order confirmation email', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            ...$order->toArray(),
            '_achievements' => $awardedCodes,
            '_outfits' => $newOutfits,
            '_serendipity' => $serendipity,
        ], 201);
    }

    /**
     * Check if COD is available for the given email/phone.
     */
    public function checkCod(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'nullable|string',
        ]);

        $blocked = Blacklist::isBlacklisted($request->email, $request->phone);

        return response()->json([
            'cod_available' => !$blocked,
            'message' => $blocked ? '由於過去有未取貨紀錄，您目前無法使用貨到付款。' : null,
        ]);
    }

    /**
     * List the authenticated customer's orders (newest first).
     */
    public function customerOrders(Request $request): JsonResponse
    {
        $customer = $request->user();
        $orders = Order::where('customer_id', $customer->id)
            ->with(['items'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($orders);
    }

    public function show(string $orderNumber, Request $request): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items', 'customer'])
            ->firstOrFail();

        // Require email verification to prevent unauthorized access
        if ($request->filled('email')) {
            if ($order->customer && $order->customer->email !== $request->email) {
                return response()->json(['message' => '查無此訂單。'], 404);
            }
        } else {
            // If no email provided, only allow authenticated users via Sanctum
            if (!$request->user() || $request->user()->id !== $order->customer_id) {
                return response()->json(['message' => '請提供訂購時使用的 Email 以查詢訂單。'], 403);
            }
        }

        return response()->json($order);
    }
}
