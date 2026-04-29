<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gamification\AchievementMirror;
use App\Services\Gamification\GroupProgressionMirror;
use App\Services\Gamification\OutfitMirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * py-service → 母艦 gamification webhook receiver. ADR-009 §2.2 / Phase B inbound.
 *
 * Signature verification + idempotency are handled upstream by
 * {@see \App\Http\Middleware\VerifyGamificationWebhookSignature}.
 *
 * Handled event_types:
 *   - gamification.level_up            → mirror customers.group_level / group_level_xp
 *   - gamification.achievement_awarded → mirror achievements row
 *   - gamification.outfit_unlocked     → merge codes into customers.outfits_owned
 *
 * Unknown types ack 200 (forward-compat for new server-side events).
 */
class GamificationWebhookController extends Controller
{
    public function __construct(
        private readonly GroupProgressionMirror $progressionMirror,
        private readonly AchievementMirror $achievementMirror,
        private readonly OutfitMirror $outfitMirror,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $eventType = (string) $request->json('event_type');
        $uuid = (string) $request->json('pandora_user_uuid');
        $payload = (array) $request->json('payload', []);

        switch ($eventType) {
            case 'gamification.level_up':
                $changed = $this->progressionMirror->applyLevelUp($uuid, $payload);

                return response()->json([
                    'status' => 'ok',
                    'event_type' => $eventType,
                    'mirrored' => $changed,
                ], 200);

            case 'gamification.achievement_awarded':
                $mirrored = $this->achievementMirror->applyAwarded($uuid, $payload);

                return response()->json([
                    'status' => 'ok',
                    'event_type' => $eventType,
                    'mirrored' => $mirrored,
                ], 200);

            case 'gamification.outfit_unlocked':
                $added = $this->outfitMirror->applyUnlocked($uuid, $payload);

                return response()->json([
                    'status' => 'ok',
                    'event_type' => $eventType,
                    'mirrored' => $added,
                ], 200);

            default:
                Log::info('[GamificationWebhook] unhandled event_type', [
                    'event_type' => $eventType,
                    'event_id' => $request->json('event_id'),
                ]);

                return response()->json([
                    'status' => 'ignored',
                    'event_type' => $eventType,
                ], 200);
        }
    }
}
