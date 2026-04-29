<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ADR-009 Phase B — outbound HTTP client that publishes gamification events
 * (engagement + achievement + subscription) from 母艦 (pandora.js-store) to
 * py-service `gamification/`.
 *
 * Mirrors the pattern in pandora-meal's GamificationPublisher: HMAC shared
 * secret, idempotent on (source_app, idempotency_key), env-gated noop, and a
 * built-in event_kind whitelist so a typo at call-site fails noisily before
 * generating an HTTP 422.
 *
 * Source app:
 *   - source_app = "jerosse" (婕樂纖, FP) per group-gamification-catalog
 *
 * Wire points (call sites that should adopt this):
 *   - AchievementService::award() — publish a `jerosse.<code>` event when a
 *     母艦 achievement is granted, so cross-app outfits / group level can
 *     unlock.
 *   - PushOrderPaidToConversion / OrderConversionObserver — already publishes
 *     to `conversion/`, may also publish gamification.engagement_deep on first
 *     paid order so visitor → loyalist progresses.
 *
 * Safe to deploy with both env vars empty — service becomes a noop.
 *
 * @see ../../docs/adr/ADR-009-cross-app-gamification.md §2-3
 */
class PandoraGamificationPublisher
{
    public const SOURCE_APP = 'jerosse';

    /**
     * Whitelist of event_kinds this publisher knows about. Catalog
     * (group-gamification-catalog.md §3) is the source of truth — adding here
     * without py-service catalog update will 422 from py-service. Listed
     * explicitly so a typo at call-site fails noisily before queuing rather
     * than 422-ing in a job retry loop.
     *
     * @var list<string>
     */
    public const KNOWN_EVENT_KINDS = [
        'jerosse.first_order',
        'jerosse.referral_signed',
        'jerosse.streak_7',
        'jerosse.streak_30',
        'jerosse.engagement_deep',
        'jerosse.achievement_awarded',
    ];

    public function isConfigured(): bool
    {
        return (string) config('services.pandora_gamification.base_url', '') !== ''
            && (string) config('services.pandora_gamification.shared_secret', '') !== '';
    }

    /**
     * Push a gamification event for a given pandora_user_uuid.
     *
     * Throws on 5xx so the queue retries; soft-fails (logs + returns false)
     * when client-side problem (no config, empty uuid, unknown event_kind)
     * to avoid stuck queue jobs.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function publish(
        string $pandoraUserUuid,
        string $eventKind,
        string $idempotencyKey,
        array $metadata = [],
        ?string $occurredAt = null,
    ): bool {
        if (! in_array($eventKind, self::KNOWN_EVENT_KINDS, true)) {
            Log::warning('[PandoraGamification] unknown event_kind, dropping', [
                'event_kind' => $eventKind,
                'uuid' => $pandoraUserUuid,
            ]);

            return false;
        }
        if ($pandoraUserUuid === '' || $idempotencyKey === '') {
            Log::warning('[PandoraGamification] empty uuid or idempotency_key', [
                'event_kind' => $eventKind,
            ]);

            return false;
        }
        if (! $this->isConfigured()) {
            Log::debug('[PandoraGamification] not configured; skipping', [
                'event_kind' => $eventKind,
            ]);

            return false;
        }

        $base = rtrim((string) config('services.pandora_gamification.base_url'), '/');
        $secret = (string) config('services.pandora_gamification.shared_secret');
        $timeout = (int) config('services.pandora_gamification.timeout', 5);

        $body = [
            'pandora_user_uuid' => $pandoraUserUuid,
            'source_app' => self::SOURCE_APP,
            'event_kind' => $eventKind,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $occurredAt ?? now()->toIso8601String(),
            'metadata' => array_filter($metadata, fn ($v) => $v !== null),
        ];

        $response = Http::withHeaders([
            'X-Internal-Secret' => $secret,
            'Accept' => 'application/json',
        ])
            ->timeout($timeout)
            ->retry(2, 200, throw: false)
            ->post($base.'/api/v1/internal/gamification/events', $body);

        if (! $response->successful()) {
            $status = $response->status();
            $bodyExcerpt = substr((string) $response->body(), 0, 200);
            // 4xx is a contract bug — log + drop (no point retrying)
            // 5xx is transient — re-throw so the queue retries
            if ($status >= 400 && $status < 500) {
                Log::warning('[PandoraGamification] 4xx; dropping', [
                    'status' => $status,
                    'body' => $bodyExcerpt,
                    'event_kind' => $eventKind,
                ]);

                return false;
            }
            throw new \RuntimeException(sprintf(
                'gamification publish 5xx: status=%d body=%s',
                $status,
                $bodyExcerpt,
            ));
        }

        return true;
    }
}
