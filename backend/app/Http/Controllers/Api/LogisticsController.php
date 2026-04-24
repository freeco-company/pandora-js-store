<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DiscordNotifier;
use App\Services\EcpayLogisticsService;
use App\Services\EcpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ECPay 物流 CVS Map store picker flow:
 *
 *  1. Frontend  → GET /api/logistics/cvs/init?sub=UNIMART  (new tab)
 *     returns auto-submit HTML form that POSTs to ECPay's map page.
 *
 *  2. User picks a store on ECPay's UI.
 *
 *  3. ECPay     → POST /api/logistics/cvs/callback
 *     we stash {store_id, store_name, address} in cache keyed by a token,
 *     then return an auto-redirect HTML pointing back at /checkout?cvs_token=XXX.
 *
 *  4. Frontend  → GET /api/logistics/cvs/pick/{token}
 *     returns the stored pick and deletes the token (single-use).
 */
class LogisticsController extends Controller
{
    private const CACHE_TTL_MINUTES = 30;

    /** Produce the auto-submit form that launches ECPay's CVS map. */
    public function init(Request $request): Response
    {
        // Subtype enum: UNIMARTC2C / FAMIC2C / HILIFEC2C / OKMARTC2C (C2C shop-to-consumer).
        // `sub=UNIMART` from frontend is remapped to C2C form.
        $raw = strtoupper($request->get('sub', 'UNIMART'));
        $sub = match ($raw) {
            'FAMI', 'FAMIC2C'       => 'FAMIC2C',
            'HILIFE', 'HILIFEC2C'   => 'HILIFEC2C',
            'OKMART', 'OKMARTC2C'   => 'OKMARTC2C',
            default                 => 'UNIMARTC2C',
        };
        $cod = $request->boolean('cod') ? 'Y' : 'N';

        $merchantId   = config('services.ecpay.merchant_id');
        $mode         = config('services.ecpay.mode', 'sandbox');
        $mapUrl = $mode === 'production'
            ? 'https://logistics.ecpay.com.tw/Express/map'
            : 'https://logistics-stage.ecpay.com.tw/Express/map';

        $frontendUrl  = rtrim(config('services.ecpay.frontend_url'), '/');
        $serverReply  = rtrim(config('app.url'), '/') . '/api/logistics/cvs/callback';

        $params = [
            'MerchantID'        => $merchantId,
            'MerchantTradeNo'   => 'CVS' . now()->format('ymdHi') . strtoupper(Str::random(4)),
            'LogisticsType'     => 'CVS',
            'LogisticsSubType'  => $sub,                // UNIMART | FAMI
            'IsCollection'      => $cod,                 // Y=COD  N=prepaid
            'ServerReplyURL'    => $serverReply,
            'ExtraData'         => $frontendUrl,        // echoed back so callback knows where to redirect
            'Device'            => 0,                    // 0=PC 1=Mobile
        ];

        $hiddenInputs = '';
        foreach ($params as $k => $v) {
            $hiddenInputs .= '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES) . '" value="' . htmlspecialchars($v, ENT_QUOTES) . '">';
        }

        $html = <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"><title>選擇超商門市…</title></head>
<body style="font-family:sans-serif;text-align:center;padding:2rem">
  <p>正在跳轉至綠界超商地圖…</p>
  <form id="cvs-form" action="{$mapUrl}" method="POST">{$hiddenInputs}</form>
  <script>document.getElementById('cvs-form').submit();</script>
</body></html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** ECPay POSTs here after the user picks a store. */
    public function callback(Request $request): Response
    {
        $storeId     = (string) $request->input('CVSStoreID', '');
        $storeName   = (string) $request->input('CVSStoreName', '');
        $address     = (string) $request->input('CVSAddress', '');
        $sub         = (string) $request->input('LogisticsSubType', '');
        $extraData   = (string) $request->input('ExtraData', config('services.ecpay.frontend_url'));

        $token = Str::random(24);
        Cache::put("cvs_pick:{$token}", [
            'store_id'   => $storeId,
            'store_name' => $storeName,
            'address'    => $address,
            'sub_type'   => $sub,                        // UNIMARTC2C / FAMIC2C / etc.
            'shipping_method' => str_starts_with($sub, 'FAMI') ? 'cvs_family' : 'cvs_711',
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        $redirect = rtrim($extraData, '/') . '/checkout?cvs_token=' . $token;

        $html = <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"><title>門市已選取</title>
<meta http-equiv="refresh" content="0; url={$redirect}"></head>
<body style="font-family:sans-serif;text-align:center;padding:2rem">
  <p>✓ 已選取 <strong>{$storeName}</strong>，正在返回結帳頁…</p>
  <script>window.location.replace('{$redirect}');</script>
</body></html>
HTML;
        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** Frontend reads the pick once + invalidates the token. */
    public function pick(string $token): JsonResponse
    {
        $key = "cvs_pick:{$token}";
        $data = Cache::get($key);
        if (! $data) {
            return response()->json(['message' => 'Token expired or invalid'], 404);
        }
        Cache::forget($key);
        return response()->json($data);
    }

    /**
     * ECPay Express/Create reply URL — they POST here asynchronously. CVS
     * Create often returns [300] 訂單處理中 synchronously WITHOUT the
     * AllPayLogisticsID; the real logistics id lands here later. We MUST
     * persist it, otherwise the order sits in "等待綠界回傳" forever
     * (2026-04-24 stuck order PD260424CW1P64 root cause).
     *
     * Also log the raw body so signature-verification failures are
     * debuggable without reproducing.
     */
    public function ecpayReply(Request $request, EcpayService $ecpay): string
    {
        $data = $request->all();

        // Always log the full payload first — if CheckMacValue fails we
        // still want to see what came in so we can backfill manually.
        Log::info('ECPay logistics reply received', [
            'data' => $data,
            'raw' => (string) $request->getContent(),
        ]);

        if (! $ecpay->verifyCallback($data, 'md5')) {
            Log::warning('ECPay logistics reply signature failed', [
                'merchant_trade_no' => $data['MerchantTradeNo'] ?? null,
                'logistics_id' => $data['AllPayLogisticsID'] ?? null,
            ]);
            return '0|CheckMacValue Error';
        }

        $rtnCode = (int) ($data['RtnCode'] ?? 0);
        $rtnMsg = (string) ($data['RtnMsg'] ?? '');
        $logisticsId = (string) ($data['AllPayLogisticsID'] ?? '');
        $merchantTradeNo = (string) ($data['MerchantTradeNo'] ?? '');

        if ($merchantTradeNo && $logisticsId) {
            $order = Order::where('order_number', $merchantTradeNo)->first();
            if ($order && empty($order->ecpay_logistics_id)) {
                $updates = [
                    'ecpay_logistics_id' => $logisticsId,
                    'logistics_status_msg' => "[{$rtnCode}] {$rtnMsg}",
                ];
                if (! empty($data['BookingNote']))      $updates['booking_note']       = (string) $data['BookingNote'];
                if (! empty($data['CVSPaymentNo']))     $updates['cvs_payment_no']     = (string) $data['CVSPaymentNo'];
                if (! empty($data['CVSValidationNo'])) $updates['cvs_validation_no'] = (string) $data['CVSValidationNo'];
                $order->update($updates);
                Log::info('ECPay logistics reply — backfilled logistics id', [
                    'order' => $order->order_number,
                    'logistics_id' => $logisticsId,
                ]);
            }
        }

        return '1|OK';
    }

    /**
     * ECPay C2C status updates (parcel arrived at store / picked up /
     * returned / etc). We mirror the status into the order row.
     *
     * Key RtnCodes (partial, from ECPay logistics spec):
     *   2030 — 商品已送達門市，待取貨   → status=shipped
     *   2031 — 消費者已取貨           → status=completed
     *   2032 — 商品已退回原門市（逾期未取）→ status=cod_no_pickup
     *   3022/3024 — 商品已送回寄件人     → status=returned
     */
    public function ecpayStatus(Request $request, EcpayService $ecpay): string
    {
        $data = $request->all();
        if (! $ecpay->verifyCallback($data, 'md5')) {
            return '0|CheckMacValue Error';
        }

        $allPayLogisticsId = (string) ($data['AllPayLogisticsID'] ?? '');
        $merchantTradeNo = (string) ($data['MerchantTradeNo'] ?? '');
        $rtnCode = (int) ($data['RtnCode'] ?? 0);
        $rtnMsg = (string) ($data['RtnMsg'] ?? '');

        $order = Order::where('ecpay_logistics_id', $allPayLogisticsId)
            ->orWhere('order_number', $merchantTradeNo)
            ->first();

        if (! $order) {
            Log::warning('ECPay logistics status for unknown order', ['data' => $data]);
            return '1|OK';
        }

        $newStatus = match (true) {
            $rtnCode === 2030 => 'shipped',
            $rtnCode === 2031 => 'completed',
            $rtnCode === 2032 => $order->payment_method === 'cod' ? 'cod_no_pickup' : 'cancelled',
            in_array($rtnCode, [3022, 3024], true) => 'cancelled',
            default => null,
        };

        $updates = ['logistics_status_msg' => "[{$rtnCode}] {$rtnMsg}"];
        if ($newStatus) {
            $updates['status'] = $newStatus;
        }
        // First time the parcel arrives at the store — anchor for "5 days
        // since arrival" pickup-reminder cron. Only set once: status can
        // momentarily flip back if there are duplicate callbacks.
        if ($rtnCode === 2030 && $order->shipped_at === null) {
            $updates['shipped_at'] = now();
        }
        $order->update($updates);

        Log::info('ECPay logistics status', [
            'order' => $order->order_number,
            'rtn_code' => $rtnCode,
            'new_status' => $newStatus,
        ]);

        if ($newStatus) {
            $statusLabels = [
                'shipped'        => ['📦 已送達門市', 0x4A9D5F],
                'completed'      => ['✅ 已取貨', 0x4A9D5F],
                'cod_no_pickup'  => ['🚫 逾期未取（貨到付款）', 0xE0748C],
                'cancelled'      => ['↩️ 已退回', 0xE8A93B],
            ];
            [$label, $color] = $statusLabels[$newStatus] ?? [$newStatus, 0x999999];
            DiscordNotifier::orders()->embed(
                title: "{$label} · {$order->order_number}",
                description: "**狀態碼**: [{$rtnCode}] {$rtnMsg}",
                color: $color,
            );
        }

        return '1|OK';
    }

    /** Admin-triggered manual shipment create. Used by the OrderResource row action. */
    public function adminCreate(int $orderId, EcpayLogisticsService $logistics): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        try {
            $result = $logistics->createCvsShipment($order);
            return response()->json(['ok' => true, 'result' => $result, 'order' => $order->fresh()]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
