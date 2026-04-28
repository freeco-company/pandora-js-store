<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PandoraConversionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ADR-008 §2.3 / §2.2 — 段 1 訊號：「婕樂纖後台收到諮詢表單 → applicant」
 *
 * **STATUS: STUB** — at the time of this PR there is no first-class
 * "franchise consultation form" UI on 婕樂纖官網. This controller exists so
 * py-service can already consume the `mothership.consultation_submitted`
 * event shape, and so once business / UX provides the real form, only the
 * persistence layer needs to be added.
 *
 * TODO (after business team confirms form fields):
 *   - Add `franchise_consultations` table (name / phone / email / source / note / pandora_user_uuid).
 *   - Validate accordingly via FormRequest.
 *   - Send Discord notification to franchise team channel.
 *   - Filament resource for follow-up tracking.
 *
 * Until then this endpoint:
 *   - accepts the minimal viable contact payload
 *   - logs it (so we don't lose anything in the meantime)
 *   - fires the conversion event so py-service flow can be E2E-tested
 */
class FranchiseConsultationController extends Controller
{
    public function store(Request $request, PandoraConversionClient $client): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'pandora_user_uuid' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:64'], // e.g. "homepage_banner"
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        // STUB: persistence is not yet implemented. Log so we don't lose
        // submissions during the stub window.
        Log::info('[FranchiseConsultation][stub] received submission', [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'has_email' => isset($data['email']),
            'has_uuid' => isset($data['pandora_user_uuid']),
            'source' => $data['source'] ?? null,
        ]);

        // Only fire the conversion event if we have a uuid to attach it to —
        // py-service keys the lifecycle FSM by uuid; an event with no subject
        // is a no-op there.
        $uuid = $data['pandora_user_uuid'] ?? null;
        if (is_string($uuid) && $uuid !== '') {
            try {
                $client->pushEvent(
                    eventId: 'mothership.consultation.'.Str::uuid()->toString(),
                    pandoraUserUuid: $uuid,
                    eventType: 'mothership.consultation_submitted',
                    payload: [
                        'source' => $data['source'] ?? null,
                        'has_phone' => true,
                        'has_email' => isset($data['email']),
                    ],
                    occurredAt: now()->toIso8601String(),
                );
            } catch (\Throwable $e) {
                // Don't fail the form submission if py-service is down. Log
                // and move on — the consultation is captured in app log
                // either way until persistence ships.
                Log::warning('[FranchiseConsultation] conversion push failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => '已收到諮詢，將盡快與您聯繫',
            'data' => ['received' => true],
        ], 202);
    }
}
