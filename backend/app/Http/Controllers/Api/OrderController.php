<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Models\Blacklist;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartPricingService;
use App\Services\DiscordNotifier;
use App\Services\EcpayLogisticsService;
use App\Services\OrderAchievementEvaluator;
use App\Services\OutfitService;
use App\Services\SerendipityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private \App\Services\AchievementService $achievements,
        private EcpayLogisticsService $logistics,
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

        $order = DB::transaction(function () use ($request, $customer, $coupon, $pricing, $discount, $total) {
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

            return $order;
        });

        $order->load('items');

        // Sync order details back onto the customer record.
        // - name / phone: only if the customer record is still default/empty or user is authenticated
        // - address book: add a new CustomerAddress row if this shipping address is new
        // Email is NOT overwritten (it's the identity, especially for Google OAuth users)
        $this->syncCustomerFromOrder($customer, $request, $order);

        // Credit-card orders are NOT paid yet — payment happens on the ECPay
        // hosted page. Don't award achievements / referral EXP until the
        // ECPay callback confirms RtnCode=1 (see PaymentController + the
        // runCelebrations helper below). COD / bank transfer still count as
        // "placed-order" events and celebrate immediately.
        $awardedCodes = [];
        $newOutfits = [];
        $serendipity = null;
        if ($order->payment_method !== 'ecpay_credit') {
            [$awardedCodes, $newOutfits, $serendipity] = $this->runCelebrations($order);
        }

        // COD + CVS orders: book logistics immediately (seller ships and
        // collects cash at pickup, so the shipment can be created at order
        // time). ecpay_credit + CVS defer to payment-callback path.
        // Gated by ECPAY_LOGISTICS_AUTO so user can sandbox-test first.
        if (
            config('services.ecpay.logistics_auto')
            && $order->payment_method === 'cod'
            && in_array($order->shipping_method, ['cvs_711', 'cvs_family'])
        ) {
            $this->tryCreateLogistics($order);
        }

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
     * Run all "order succeeded" side-effects: referral reward, achievement
     * evaluation, outfit unlocks, serendipity roll. Idempotent — safe to
     * call from both the store() fast-path (for COD/bank transfer) AND from
     * the ECPay callback once RtnCode=1 (for credit-card orders).
     *
     * @return array{0: array<int,string>, 1: array<int,string>, 2: ?array}
     */
    /**
     * Safely attempt CVS shipment creation. Never raises — a failed
     * shipment create must not block the order from being created. The
     * admin will see the error in logistics_status_msg + Dashboard
     * "待建立物流" widget and can retry manually.
     */
    public function tryCreateLogistics(Order $order): void
    {
        try {
            $this->logistics->createCvsShipment($order);
        } catch (\Throwable $e) {
            Log::warning('CVS auto-logistics create failed, will need manual retry', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
            DiscordNotifier::orders()->embed(
                title: "⚠️ 物流建立失敗 · {$order->order_number}",
                description: "需從後台手動重建物流\n**錯誤**: " . mb_substr($e->getMessage(), 0, 200),
                color: 0xE8A93B,
            );
        }
    }

    public function runCelebrations(Order $order): array
    {
        $customer = Customer::findOrFail($order->customer_id);

        // Prior-order count EXCLUDES this one (even if it's been created) —
        // that's what first-order referral logic expects.
        $priorOrderCount = (int) Order::where('customer_id', $customer->id)
            ->where('id', '!=', $order->id)
            ->count();

        $this->processReferralReward($customer, $priorOrderCount);

        $coupon = $order->coupon_id ? Coupon::find($order->coupon_id) : null;
        $awarded = $this->evaluator->evaluate($customer->fresh(), $order, $coupon !== null);
        $outfits = $this->outfitService->checkUnlocks($customer->fresh());
        $serendipity = $this->serendipity->maybeGenerate($customer->fresh());

        return [$awarded, $outfits, $serendipity];
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
    /**
     * Grant referral achievements on the referred customer's FIRST successful order.
     * Idempotent via Customer::referral_reward_granted.
     */
    private function processReferralReward(Customer $customer, int $priorOrderCount): void
    {
        if ($priorOrderCount > 0) return;                           // only on very first order
        if ($customer->referral_reward_granted) return;             // already processed
        if (! $customer->referred_by_customer_id) return;

        $referrer = Customer::find($customer->referred_by_customer_id);
        if (! $referrer || $referrer->id === $customer->id) return; // self-ref guard

        // Mark done so we never re-award
        $customer->update(['referral_reward_granted' => true]);

        // Reward the referred (new) customer
        $this->achievements->award($customer, \App\Services\AchievementCatalog::FIRST_REFERRED);

        // Reward the referrer — tier on cumulative successful referrals
        $this->achievements->award($referrer, \App\Services\AchievementCatalog::FIRST_REFERRAL);
        $successCount = Customer::where('referred_by_customer_id', $referrer->id)
            ->where('referral_reward_granted', true)->count();
        if ($successCount >= 3)  $this->achievements->award($referrer, \App\Services\AchievementCatalog::REFERRAL_3);
        if ($successCount >= 10) $this->achievements->award($referrer, \App\Services\AchievementCatalog::REFERRAL_10);
    }

    /**
     * Persist the customer-facing details from an order back onto the account:
     *   - update name/phone on the Customer row (if user didn't have them set)
     *   - add a new address to the address book if this shipping address is new
     *
     * Email is intentionally not touched (Google OAuth identity).
     */
    private function syncCustomerFromOrder(Customer $customer, Request $request, \App\Models\Order $order): void
    {
        // Update profile fields if missing / stale
        $dirty = false;
        $postedName = (string) $request->input('customer.name');
        $postedPhone = (string) $request->input('customer.phone');

        if ($postedName && $customer->name !== $postedName) {
            $customer->name = $postedName;
            $dirty = true;
        }
        if ($postedPhone && $customer->phone !== $postedPhone) {
            $customer->phone = $postedPhone;
            $dirty = true;
        }
        if ($dirty) $customer->save();

        // Only record a street address when the order actually carries one
        // (CVS-pickup orders store store_id/store_name instead)
        $street = trim((string) ($order->shipping_address ?? ''));
        if ($street === '') return;

        $recipient = trim((string) ($order->shipping_name ?? $customer->name));
        $phone = trim((string) ($order->shipping_phone ?? $customer->phone));

        // Dedupe: skip if exact same street already exists
        $exists = $customer->addresses()
            ->where('street', $street)
            ->where('recipient_name', $recipient)
            ->exists();
        if ($exists) return;

        // First-ever address auto-becomes default
        $isFirst = $customer->addresses()->count() === 0;

        CustomerAddress::create([
            'customer_id'    => $customer->id,
            'recipient_name' => $recipient,
            'phone'          => $phone,
            'street'         => $street,
            'is_default'     => $isFirst,
        ]);
    }

    public function customerOrders(Request $request): JsonResponse
    {
        $customer = $request->user();
        $orders = Order::where('customer_id', $customer->id)
            ->with(['items.product:id,slug,image'])                 // image+slug for list thumbs
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        // Flatten product.image + product.slug onto each item (FE reads item.image / item.slug)
        $orders->getCollection()->transform(function ($o) {
            $o->items->transform(function ($it) {
                $p = $it->product;
                $it->setAttribute('image', $p?->image);
                $it->setAttribute('slug', $p?->slug);
                unset($it->product);
                return $it;
            });
            return $o;
        });

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
