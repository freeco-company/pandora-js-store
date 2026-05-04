<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Services\Streak\DailyLoginStreakService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * SPEC-cross-app-streak Phase 1.C — record per-request login streak（母艦）。
 *
 * 跑在 auth 之後（$request->user() 已 set）。同一日內 idempotent —— 第一次
 * authenticated hit 漲 streak，後續 hit no-op。結果 attach 在 response header
 * `X-Streak`（JSON）讓 frontend 不用再 round-trip 也能拿到，並 stash 在 request
 * attribute `daily_streak`。
 *
 * Fail-soft：任何例外都吞 + log，永遠不擋 request（streak 不該擋電商結帳）。
 */
class RecordDailyStreak
{
    public function __construct(
        private readonly DailyLoginStreakService $service,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $customer = $request->user();
        if (! $customer instanceof Customer) {
            return $response;
        }

        try {
            $result = $this->service->recordLogin($customer);
            $request->attributes->set('daily_streak', $result);

            // Header is JSON-encoded so frontend can `JSON.parse(headers.get('X-Streak'))`.
            $response->headers->set('X-Streak', (string) json_encode($result, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            Log::warning('[RecordDailyStreak] failed', [
                'customer_id' => $customer->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
