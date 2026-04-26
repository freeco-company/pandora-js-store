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
            'items.*.type' => 'nullable|string|in:product,bundle',
            'items.*.product_id' => 'required_unless:items.*.type,bundle|nullable|integer|exists:products,id',
            'items.*.bundle_id' => 'required_if:items.*.type,bundle|nullable|integer|exists:bundles,id',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'customer.name' => 'required|string|max:100',
            'customer.email' => 'required|email:rfc',
            'customer.phone' => 'required|string|regex:/^09\d{8}$/',
            'payment_method' => 'required|in:ecpay_credit,cod,bank_transfer',
            'shipping_method' => 'required|in:cvs_711,cvs_family,home_delivery',
            'shipping_name' => 'required|string|max:100',
            'shipping_phone' => 'required|string',
            'shipping_address' => 'required_if:shipping_method,home_delivery|nullable|string|max:500',
            'shipping_store_id' => 'required_if:shipping_method,cvs_711,cvs_family|nullable|string',
            'shipping_store_name' => 'required_if:shipping_method,cvs_711,cvs_family|nullable|string',
            'coupon_code' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:64',
            // First-touch attribution from the frontend (lib/attribution).
            // Tells /admin/orders which campaign / social post drove this sale.
            'referer_source' => 'nullable|string|max:32',
            'utm_source' => 'nullable|string|max:64',
            'utm_medium' => 'nullable|string|max:64',
            'utm_campaign' => 'nullable|string|max:128',
            'landing_path' => 'nullable|string|max:255',
        ]);

        // Normalize email: trim + lowercase to prevent duplicate customers
        $request->merge([
            'customer' => array_merge($request->input('customer'), [
                'email' => strtolower(trim($request->input('customer.email'))),
            ]),
        ]);

        // Idempotency: reject duplicate submissions within 5 minutes
        $idempotencyKey = $request->input('idempotency_key');
        if ($idempotencyKey) {
            $cacheKey = "order_idem:{$idempotencyKey}";
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $existingOrderNumber = \Illuminate\Support\Facades\Cache::get($cacheKey);
                $existingOrder = Order::where('order_number', $existingOrderNumber)->first();
                if ($existingOrder) {
                    return response()->json([
                        'message' => '訂單已建立',
                        'order' => $existingOrder->load('items'),
                    ], 200);
                }
            }
        }

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

        // Re-calculate pricing server-side (also validates product availability)
        $pricing = $this->pricingService->calculate($request->items);

        // Block order if any items are unavailable (deactivated, out of stock, etc.)
        if (!empty($pricing['unavailable'])) {
            $names = collect($pricing['unavailable'])->pluck('name')->join('、');
            return response()->json([
                'message' => "以下商品無法購買：{$names}。請移除後重新結帳。",
                'unavailable' => $pricing['unavailable'],
            ], 422);
        }

        if (empty($pricing['items']) && empty($pricing['bundles'])) {
            return response()->json(['message' => '購物車中沒有可購買的商品。'], 422);
        }

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

        // Find or create customer.
        //
        // 三條路徑（避免製造重複帳號 — 重要！）：
        //   1. 已登入（Sanctum bearer 解得到 user）：直接用認證身分，**忽略**結帳表單填的 email。
        //      避免「Google 登入後在結帳填了不同 email → 被當訪客結帳建第二個帳號」這個常見漏洞。
        //   2. 未登入但 phone 比對到一個 email 還是 `@line.user` placeholder 的 customer：
        //      認定為同一人（LINE 登入過 → 後來用訪客結帳補真實 email），升級該 row 的
        //      placeholder email 為實填 email，並複用該 customer。
        //   3. 都沒有：用實填 email 建新 customer（與舊行為一致）。
        //
        // 為什麼 phone fallback 只信 `@line.user` placeholder：phone 不是 unique，可能多人
        // 共用同一支電話（家人、員工代下單）。只在「對方明顯是 placeholder 待補完」這個
        // 強訊號下才合併，避免把無關帳號黏在一起。
        $auth = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        $typedEmail = $request->input('customer.email');
        $typedPhone = $request->input('customer.phone');

        if ($auth) {
            $customer = $auth;
            // 登入用戶若 email 仍是 LINE placeholder、且 typed email 是真實未被佔用 → 順手升級。
            // 其他情況不動 email（保留 Google / 自填 email 為唯一身分鍵）。
            if (
                str_ends_with((string) $customer->email, '@line.user')
                && $typedEmail
                && !str_ends_with($typedEmail, '@line.user')
                && !Customer::where('email', $typedEmail)->where('id', '!=', $customer->id)->exists()
            ) {
                $customer->email = $typedEmail;
                $customer->save();
            }
        } else {
            $customer = Customer::where('email', $typedEmail)->first();

            if (!$customer && $typedPhone) {
                $candidate = Customer::where('phone', $typedPhone)
                    ->where('email', 'like', '%@line.user')
                    ->first();
                // 雙重保險：若 typed email 已被別 row 佔用，不要強行覆蓋 placeholder
                // （unique 會炸），改走「建新 customer」路徑。
                if (
                    $candidate
                    && !Customer::where('email', $typedEmail)->where('id', '!=', $candidate->id)->exists()
                ) {
                    $candidate->email = $typedEmail;
                    $candidate->save();
                    $customer = $candidate;
                }
            }

            if (!$customer) {
                $customer = Customer::create([
                    'email' => $typedEmail,
                    'name' => $request->input('customer.name'),
                    'phone' => $typedPhone,
                    'password' => bcrypt(Str::random(16)),
                ]);
            }
        }

        $total = $pricing['total'] - $discount;

        try {
            $order = DB::transaction(function () use ($request, $customer, $coupon, $pricing, $discount, $total) {
                // Aggregate stock requirements from product items + bundle items.
                // A bundle contributes buy_qty × bundle_qty AND gift_qty × bundle_qty
                // to the product's stock deduction, since we ship both.
                $stockDemand = [];
                foreach ($pricing['items'] as $item) {
                    $stockDemand[$item['product_id']] = ($stockDemand[$item['product_id']] ?? 0) + $item['quantity'];
                }
                foreach ($pricing['bundles'] ?? [] as $bundle) {
                    foreach (array_merge($bundle['buy_items'], $bundle['gift_items']) as $bi) {
                        $stockDemand[$bi['product_id']] = ($stockDemand[$bi['product_id']] ?? 0)
                            + $bi['quantity'] * $bundle['quantity'];
                    }
                }
                foreach ($stockDemand as $productId => $demanded) {
                    $product = Product::lockForUpdate()->find($productId);
                    if ($product->stock_quantity && $demanded > $product->stock_quantity) {
                        throw new \RuntimeException("「{$product->name}」庫存不足（剩 {$product->stock_quantity}）");
                    }
                    if ($product->stock_quantity) {
                        $product->decrement('stock_quantity', $demanded);
                        if ($product->stock_quantity <= 0) {
                            $product->update(['stock_status' => 'outofstock']);
                        }
                    }
                }

                // Lock coupon to prevent race condition on max_uses
                if ($coupon) {
                    $coupon = Coupon::lockForUpdate()->find($coupon->id);
                    if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
                        throw new \RuntimeException('優惠碼已達使用上限。');
                    }
                    $coupon->increment('used_count');
                }

                $order = Order::create([
                    'order_number' => 'PD' . now()->format('ymd') . strtoupper(Str::random(6)),
                    'customer_id' => $customer->id,
                    'coupon_id' => $coupon?->id,
                    'status' => 'pending',
                    'pricing_tier' => $pricing['tier'],
                    'subtotal' => $pricing['total'],
                    'shipping_fee' => 0,
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
                    'referer_source' => $request->input('referer_source'),
                    'utm_source' => $request->input('utm_source'),
                    'utm_medium' => $request->input('utm_medium'),
                    'utm_campaign' => $request->input('utm_campaign'),
                    'landing_path' => $request->input('landing_path'),
                ]);

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

                // Bundle items get exploded into order_items. Buy items carry
                // their VIP share of the bundle price; gift items are recorded
                // at price=0 so shipping/fulfillment has a full manifest.
                foreach ($pricing['bundles'] ?? [] as $bundle) {
                    $bundleQty = $bundle['quantity'];
                    $buyCount = collect($bundle['buy_items'])->sum('quantity');
                    foreach ($bundle['buy_items'] as $bi) {
                        // Distribute bundle_price proportionally across buy items.
                        $perUnit = $buyCount > 0 ? $bundle['unit_price'] / $buyCount : 0;
                        $qty = $bi['quantity'] * $bundleQty;
                        $order->items()->create([
                            'product_id' => $bi['product_id'],
                            'product_name' => '【' . $bundle['name'] . '】' . $bi['name'],
                            'quantity' => $qty,
                            'unit_price' => round($perUnit, 2),
                            'subtotal' => round($perUnit * $qty, 2),
                            'created_at' => now(),
                        ]);
                    }
                    foreach ($bundle['gift_items'] as $gi) {
                        $qty = $gi['quantity'] * $bundleQty;
                        $order->items()->create([
                            'product_id' => $gi['product_id'],
                            'product_name' => '【' . $bundle['name'] . '｜贈品】' . $gi['name'],
                            'quantity' => $qty,
                            'unit_price' => 0,
                            'subtotal' => 0,
                            'created_at' => now(),
                        ]);
                    }
                }

                return $order;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Cache idempotency key so duplicate submissions return the same order
        if ($idempotencyKey) {
            \Illuminate\Support\Facades\Cache::put("order_idem:{$idempotencyKey}", $order->order_number, now()->addMinutes(5));
        }

        $order->load('items');

        // Sync order details back onto the customer record.
        // - name / phone: only if the customer record is still default/empty or user is authenticated
        // - address book: add a new CustomerAddress row if this shipping address is new
        // Email is NOT overwritten (it's the identity, especially for Google OAuth users)
        $this->syncCustomerFromOrder($customer, $request, $order);

        // Celebrations (achievements / referral reward / outfit unlocks) only
        // fire once payment is confirmed — otherwise we'd have to revoke when
        // a bank transfer never arrives. COD celebrates immediately because
        // intent is firm (shipment ships before cash is collected).
        //   - ecpay_credit: ECPay callback with RtnCode=1 triggers runCelebrations
        //   - bank_transfer: OrderObserver triggers on payment_status→paid
        //   - cod: celebrate now
        $awardedCodes = [];
        $newOutfits = [];
        $serendipity = null;
        if ($order->payment_method === 'cod') {
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
