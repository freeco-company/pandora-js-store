<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
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
        $sub = strtoupper($request->get('sub', 'UNIMART'));        // UNIMART | FAMI
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
            'sub_type'   => $sub,                        // UNIMART / FAMI
            'shipping_method' => $sub === 'FAMI' ? 'cvs_family' : 'cvs_711',
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
}
