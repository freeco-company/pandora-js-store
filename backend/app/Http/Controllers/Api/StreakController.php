<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Streak\DailyLoginStreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SPEC-cross-app-streak Phase 1.C — read-only streak endpoint（母艦）。
 *
 * GET /api/streak/today — 回傳當前 streak + 一次 recordLogin() 結果，讓 FE
 * 在 app boot 一次 round-trip 決定要不要顯示 toast（is_first_today / is_milestone）。
 */
class StreakController extends Controller
{
    public function __construct(
        private readonly DailyLoginStreakService $service,
    ) {}

    public function today(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $recorded = $this->service->recordLogin($customer);
        $snapshot = $this->service->snapshot($customer);

        return response()->json([
            'current_streak' => $recorded['streak'],
            'longest_streak' => max($recorded['longest_streak'], $snapshot['longest_streak']),
            'is_first_today' => $recorded['is_first_today'],
            'is_milestone' => $recorded['is_milestone'],
            'milestone_label' => $recorded['milestone_label'],
            'today_date' => $recorded['today_date'],
        ]);
    }
}
