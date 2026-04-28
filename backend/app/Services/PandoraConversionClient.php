<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ADR-008 §2.3 — outbound HTTP client that pushes lifecycle-relevant events
 * from 母艦 (pandora.js-store) to py-service `conversion/`.
 *
 * Mirrors the inbound HMAC signing pattern from `VerifyConversionInternalSignature`
 * but with a body-aware base string:
 *     "{timestamp}.{POST}.{path}.{sha256(body)}"
 *
 * Why include sha256(body):
 *   - Inbound is GET (no body) so signing METHOD+PATH suffices.
 *   - Outbound is POST with semantic body — without body in the sig, an
 *     attacker who captured a valid timestamp+sig could swap event_type or
 *     payload during the 5-minute window.
 *   - py-service must verify with the same scheme.
 *
 * Behaviour when not configured (`PANDORA_CONVERSION_BASE_URL` empty):
 *   - Returns `false` and logs a debug line. Never throws. The caller (queued
 *     listener) treats this as "noop, don't retry".
 */
class PandoraConversionClient
{
    public function isConfigured(): bool
    {
        return (string) config('services.pandora_conversion.base_url') !== ''
            && (string) config('services.pandora_conversion.internal_secret') !== '';
    }

    /**
     * POST a conversion event to py-service.
     *
     * @param  string  $eventId  caller-provided idempotency key (py-service dedups by this)
     * @param  string  $eventType  e.g. "mothership.first_order" / "mothership.order_paid" / "mothership.consultation_submitted"
     * @param  array<string, mixed>  $payload
     */
    public function pushEvent(
        string $eventId,
        string $pandoraUserUuid,
        string $eventType,
        array $payload,
        string $occurredAt,
    ): bool {
        if (! $this->isConfigured()) {
            Log::debug('[PandoraConversion] base_url/secret not configured; skipping push', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return false;
        }

        $body = [
            'event_id' => $eventId,
            'pandora_user_uuid' => $pandoraUserUuid,
            'app_id' => (string) config('services.pandora_conversion.app_id', 'fairy_pandora'),
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => $occurredAt,
        ];

        // Stable, deterministic JSON for signing. Must match what we ship on the
        // wire, so we serialize once and reuse.
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($bodyJson === false) {
            Log::error('[PandoraConversion] failed to encode body json', ['event_id' => $eventId]);

            return false;
        }

        $timestamp = (string) time();
        $bodyHash = hash('sha256', $bodyJson);
        $signBase = $timestamp.'.POST./api/v1/internal/events.'.$bodyHash;
        $signature = hash_hmac(
            'sha256',
            $signBase,
            (string) config('services.pandora_conversion.internal_secret'),
        );

        $url = rtrim((string) config('services.pandora_conversion.base_url'), '/').'/api/v1/internal/events';
        $timeout = (int) config('services.pandora_conversion.push_timeout_seconds', 5);

        $response = Http::withHeaders([
            'X-Pandora-Timestamp' => $timestamp,
            'X-Pandora-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])
            ->timeout($timeout)
            ->withBody($bodyJson, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            // Throw so the queue worker retries via job backoff. 4xx (except 429)
            // could indicate a permanent contract drift — listener will give up
            // after `tries` is exhausted and route to `failed_jobs` for ops.
            throw new \RuntimeException(sprintf(
                'pandora-conversion push failed: status=%d body=%s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        return true;
    }
}
