<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-003 §3.2 — internal signed endpoint guard for **outbound queries**
 * coming from py-service (`HttpMothershipOrderClient`).
 *
 * Why a separate middleware from `VerifyIdentityWebhookSignature`:
 *   - identity webhook is **POST + body** (signs `{ts}.{event_id}.{body}`) and
 *     uses a nonce table to dedup re-deliveries.
 *   - This is **GET** (idempotent, no body) — we sign `{ts}.{method}.{path}`.
 *     No nonce needed; replay of a GET inside the timestamp window is harmless.
 *
 * Three checks:
 *   1. timestamp ±5min (replay window)
 *   2. HMAC-SHA256 over "{timestamp}.{METHOD}.{path}" (path == request URI path,
 *      no query string — keeps signing deterministic vs request-uri encoding).
 *   3. constant-time compare via hash_equals.
 *
 * Secret env: `PANDORA_CONVERSION_INTERNAL_SECRET`. Missing/empty → 401 (fail
 * closed: never serve unauth'd internal data even if env is misconfigured).
 *
 * py-service signs the same base string in `HttpMothershipOrderClient`.
 */
class VerifyConversionInternalSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.pandora_conversion.internal_secret');
        if ($secret === '') {
            // Fail closed. Logged once per request — ops should see this and
            // either set the env or remove the route.
            Log::error('[ConversionInternal] missing PANDORA_CONVERSION_INTERNAL_SECRET');

            return response()->json(['error' => 'unauthorized'], 401);
        }

        $timestamp = (string) $request->header('X-Pandora-Timestamp', '');
        $signature = (string) $request->header('X-Pandora-Signature', '');

        if ($timestamp === '' || $signature === '') {
            return response()->json(['error' => 'missing signature headers'], 401);
        }

        // 1. timestamp window
        $window = (int) config('services.pandora_conversion.window_seconds', 300);
        if (! ctype_digit($timestamp)) {
            return response()->json(['error' => 'bad timestamp'], 401);
        }
        $diff = abs(time() - (int) $timestamp);
        if ($diff > $window) {
            return response()->json(['error' => 'timestamp out of window'], 401);
        }

        // 2 + 3. HMAC over "{ts}.{METHOD}.{path}". `getPathInfo()` returns
        // the path without query string, decoded — matches what httpx sends as
        // request.url.path on the py-service side.
        $base = $timestamp.'.'.strtoupper($request->getMethod()).'.'.$request->getPathInfo();
        $expected = hash_hmac('sha256', $base, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'signature mismatch'], 401);
        }

        return $next($request);
    }
}
