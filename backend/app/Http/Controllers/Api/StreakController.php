<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Streak\DailyLoginStreakService;
use App\Services\Streak\GroupStreakClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SPEC-cross-app-streak Phase 1.C + 5B — read-only streak endpoint（母艦）。
 *
 * GET /api/streak/today — 回傳當前 streak + 一次 recordLogin() 結果，讓 FE
 * 在 app boot 一次 round-trip 決定要不要顯示 toast（is_first_today / is_milestone）。
 *
 * Phase 5B：額外 overlay 集團 master streak（cross-App，pandora-core-conversion
 * `/internal/group-streak/{uuid}`）。本 endpoint 同時 proxy 該值，前端 milestone
 * variant 會用「FP 團隊連續第 N 天」副標顯示。fail-soft：拿不到就 group=null。
 */
class StreakController extends Controller
{
    public function __construct(
        private readonly DailyLoginStreakService $service,
        private readonly GroupStreakClient $groupStreakClient,
    ) {}

    public function today(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $recorded = $this->service->recordLogin($customer);
        $snapshot = $this->service->snapshot($customer);

        // Phase 5B — overlay 集團 master streak。沒綁 Pandora Core uuid 直接 skip
        // （client 內也檢，雙保險避免無謂的 cache key）。
        $uuid = (string) ($customer->pandora_user_uuid ?? '');
        $group = $uuid !== '' ? $this->groupStreakClient->fetch($uuid) : null;

        return response()->json([
            'current_streak' => $recorded['streak'],
            'longest_streak' => max($recorded['longest_streak'], $snapshot['longest_streak']),
            'is_first_today' => $recorded['is_first_today'],
            'is_milestone' => $recorded['is_milestone'],
            'milestone_label' => $recorded['milestone_label'],
            'today_date' => $recorded['today_date'],
            // SPEC-streak-milestone-rewards — only present when this call hit a
            // milestone (is_first_today + streak ∈ MILESTONES). Frontend uses
            // it to render reward chips (achievement badge + coupon code).
            'unlocks' => $recorded['unlocks'] ?? null,
            // SPEC-cross-app-streak Phase 5B — null when not configured / not
            // bound / py-service unavailable. Frontend treats null as "skip
            // overlay subline" without crashing.
            'group' => $group,
        ]);
    }
}
