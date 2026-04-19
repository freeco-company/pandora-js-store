<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AchievementCatalog;
use App\Services\AchievementProgressCalculator;
use App\Services\AchievementService;
use App\Services\OutfitCatalog;
use App\Services\OutfitService;
use App\Services\SerendipityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private AchievementService $achievements,
        private OutfitService $outfits,
        private SerendipityService $serendipity,
        private AchievementProgressCalculator $progress,
    ) {}

    /**
     * Gamification dashboard: streak, achievements, outfits, activation progress.
     * Bumps streak as a side-effect of visiting.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $customer = $request->user();

        $streakAward = $this->achievements->bumpStreak($customer);
        $customer->refresh();

        $newOutfits = $this->outfits->checkUnlocks($customer);
        $serendipity = $this->serendipity->maybeGenerate($customer);

        $earned = $customer->achievements()->get(['code', 'awarded_at']);
        $ownedOutfits = $customer->outfits()->get(['code', 'unlocked_at']);

        // XP: bronze=10, silver=25, gold=50, computed from earned achievements
        $xpByTier = ['bronze' => 10, 'silver' => 25, 'gold' => 50];
        $catalog = AchievementCatalog::all();
        $totalXp = $earned->sum(function ($a) use ($catalog, $xpByTier) {
            $tier = $catalog[$a->code]['tier'] ?? 'bronze';
            return $xpByTier[$tier] ?? 10;
        });
        $level = (int) floor($totalXp / 100) + 1;
        $xpInLevel = $totalXp % 100;

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'streak_days' => $customer->streak_days,
                'total_orders' => $customer->total_orders,
                'total_spent' => (int) $customer->total_spent,
                'total_xp' => $totalXp,
                'level' => $level,
                'xp_in_level' => $xpInLevel,
                'referral_code' => $customer->referral_code,
                'current_outfit' => $customer->current_outfit,
                'current_backdrop' => $customer->current_backdrop,
                'activation_progress' => $customer->activation_progress ?? [],
            ],
            'achievements' => [
                'earned' => $earned,
                'catalog' => AchievementCatalog::all(),
                'progress' => $this->progress->forCustomer($customer),
            ],
            'outfits' => [
                'owned' => $ownedOutfits,
                'catalog' => OutfitCatalog::all(),
                'backdrops' => OutfitCatalog::backdrops(),
            ],
            '_achievements' => $streakAward ? [$streakAward] : [],
            '_outfits' => $newOutfits,
            '_serendipity' => $serendipity,
        ]);
    }

    public function setOutfit(Request $request): JsonResponse
    {
        $request->validate(['code' => 'nullable|string']);
        $ok = $this->outfits->setCurrentOutfit($request->user(), $request->input('code'));
        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }

    public function setBackdrop(Request $request): JsonResponse
    {
        $request->validate(['code' => 'nullable|string']);
        $ok = $this->outfits->setCurrentBackdrop($request->user(), $request->input('code'));
        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }

    /**
     * Mark an activation step (e.g., first_browse, first_cart) — fire-and-forget from client.
     */
    public function markActivation(Request $request): JsonResponse
    {
        $request->validate(['step' => 'required|string|in:first_browse,first_article,first_brand,first_cart,first_order,first_mascot']);
        $step = $request->input('step');
        $customer = $request->user();

        $this->achievements->markActivation($customer, $step);

        // Every browse-based step awards its matching achievement.
        // award() is idempotent; returns the code only on first award, null otherwise.
        $codeMap = [
            'first_browse'  => AchievementCatalog::FIRST_BROWSE,
            'first_article' => AchievementCatalog::FIRST_ARTICLE,
            'first_brand'   => AchievementCatalog::FIRST_BRAND,
            'first_cart'    => AchievementCatalog::FIRST_CART,
            'first_mascot'  => AchievementCatalog::FIRST_MASCOT,
            'first_order'   => AchievementCatalog::FIRST_ORDER,
        ];
        $awarded = isset($codeMap[$step]) ? $this->achievements->award($customer, $codeMap[$step]) : null;

        return response()->json(['_achievement' => $awarded]);
    }
}
