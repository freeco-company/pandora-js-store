<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FranchiseConsultation;
use App\Services\DiscordNotifier;
use App\Services\PandoraConversionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ADR-008 §2.2 段 1 訊號 — 「婕樂纖後台收到諮詢表單 → applicant」
 *
 * Persists the submission to `franchise_consultations` for follow-up,
 * notifies the franchise team via Discord, and (if a pandora_user_uuid is
 * attached) fires the `mothership.consultation_submitted` event to py-service
 * for the lifecycle FSM transition.
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
            'source' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = FranchiseConsultation::create([
            'pandora_user_uuid' => $data['pandora_user_uuid'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'source' => $data['source'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => 'new',
        ]);

        // Discord notify — soft-fail; persistence + py-service push must
        // not depend on Discord being up.
        try {
            DiscordNotifier::franchise()->embed(
                title: '🌿 新加盟諮詢',
                description: sprintf("**%s**\n📞 %s%s",
                    $data['name'],
                    $data['phone'],
                    isset($data['email']) ? "\n✉️ {$data['email']}" : ''),
                fields: array_values(array_filter([
                    isset($data['source']) ? ['name' => '來源', 'value' => $data['source'], 'inline' => true] : null,
                    isset($data['pandora_user_uuid']) ? ['name' => 'UUID', 'value' => substr((string) $data['pandora_user_uuid'], 0, 12).'…', 'inline' => true] : null,
                    isset($data['note']) ? ['name' => '備註', 'value' => mb_substr($data['note'], 0, 1000)] : null,
                    ['name' => 'Lead #', 'value' => (string) $row->id, 'inline' => true],
                ])),
                color: 0x9F6B3E,
            );
        } catch (\Throwable $e) {
            Log::warning('[FranchiseConsultation] Discord notify failed', ['error' => $e->getMessage()]);
        }

        // py-service lifecycle event — only fires when uuid is set.
        $uuid = $data['pandora_user_uuid'] ?? null;
        if (is_string($uuid) && $uuid !== '') {
            try {
                $client->pushEvent(
                    eventId: 'mothership.consultation.'.Str::uuid()->toString(),
                    pandoraUserUuid: $uuid,
                    eventType: 'mothership.consultation_submitted',
                    payload: [
                        'consultation_id' => $row->id,
                        'source' => $data['source'] ?? null,
                        'has_phone' => true,
                        'has_email' => isset($data['email']),
                    ],
                    occurredAt: now()->toIso8601String(),
                );
            } catch (\Throwable $e) {
                Log::warning('[FranchiseConsultation] conversion push failed', [
                    'consultation_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => '已收到諮詢，將盡快與您聯繫',
            'data' => ['received' => true, 'consultation_id' => $row->id],
        ], 202);
    }
}
