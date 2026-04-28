<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Identity\Webhook\IdentityUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2 (ADR-007 / pandora-js-store#11)：接 platform 推來的 user.upserted
 * 等 webhook，把 customers 同步成 platform 的 mirror。
 *
 * 簽章 / replay 防護由 VerifyIdentityWebhookSignature middleware 處理；
 * 走到這層時 event_id 已 dedup、簽章已驗。本層只負責業務 upsert。
 */
class IdentityWebhookController extends Controller
{
    public function __construct(private IdentityUpsertService $upsertService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $type = (string) $request->input('type', '');
        $data = $request->input('data', []);

        if (! is_array($data) || $data === []) {
            return response()->json(['error' => 'invalid payload: data missing'], 422);
        }

        try {
            switch ($type) {
                case 'user.upserted':
                case 'user.suspended':
                case 'user.merged':
                    $customer = $this->upsertService->upsert($data);
                    if ($customer === null) {
                        return response()->json([
                            'status' => 'skipped',
                            'reason' => 'insufficient identity data (email/phone missing)',
                        ]);
                    }

                    return response()->json([
                        'status' => 'ok',
                        'customer_id' => $customer->id,
                        'pandora_user_uuid' => $customer->pandora_user_uuid,
                    ]);

                default:
                    Log::warning('[IdentityWebhook] unknown event type', ['type' => $type]);

                    return response()->json(['error' => "unknown event type: {$type}"], 422);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
