<?php

namespace App\Services;

use App\Models\DiscordNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Discord webhook client.
 * Configure via env: DISCORD_COMPLIANCE_WEBHOOK=https://discord.com/api/webhooks/...
 * No-op if env is missing (safe in dev/test).
 */
class DiscordNotifier
{
    public function __construct(
        private readonly ?string $webhook = null,
        private readonly string $channel = 'unknown',
    ) {}

    public static function compliance(): self
    {
        return new self(config('services.discord.compliance_webhook'), 'compliance');
    }

    /** Webhook for new-order alerts — falls back to compliance if unset. */
    public static function orders(): self
    {
        return new self(
            config('services.discord.orders_webhook')
                ?: config('services.discord.compliance_webhook'),
            'orders',
        );
    }

    /** Webhook for Google Ads daily reports — falls back to orders → compliance. */
    public static function ads(): self
    {
        return new self(
            config('services.discord.ads_webhook')
                ?: config('services.discord.orders_webhook')
                ?: config('services.discord.compliance_webhook'),
            'ads',
        );
    }

    /**
     * Webhook for Claude-generated strategy analysis. Kept separate from
     * ads() so the raw-numbers channel stays clean. Falls back to ads()
     * channel if unset so it still works on partially-configured envs.
     */
    public static function adsStrategy(): self
    {
        return new self(
            config('services.discord.ads_strategy_webhook')
                ?: config('services.discord.ads_webhook')
                ?: config('services.discord.orders_webhook')
                ?: config('services.discord.compliance_webhook'),
            'ads_strategy',
        );
    }

    /** Webhook for new franchise consultation leads. Falls back to orders. */
    public static function franchise(): self
    {
        return new self(
            config('services.discord.franchise_webhook')
                ?: config('services.discord.orders_webhook')
                ?: config('services.discord.compliance_webhook'),
            'franchise',
        );
    }

    public function isEnabled(): bool
    {
        return ! empty($this->webhook);
    }

    /**
     * Send a rich embed message.
     *
     * @param  string  $title
     * @param  string  $description Plain text / markdown, ≤ 4000 chars
     * @param  array<int, array{name:string,value:string,inline?:bool}>  $fields
     * @param  int  $color  Decimal color, e.g. 0x9F6B3E = 10447166
     */
    public function embed(string $title, string $description, array $fields = [], int $color = 10447166): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $success = false;
        $status = null;
        try {
            $res = Http::timeout(10)->post($this->webhook, [
                'embeds' => [[
                    'title'       => mb_substr($title, 0, 256),
                    'description' => mb_substr($description, 0, 4000),
                    'color'       => $color,
                    'fields'      => array_slice($fields, 0, 25),
                    'timestamp'   => now()->toIso8601String(),
                ]],
            ]);
            $status = $res->status();
            $success = $res->successful();
            if (! $success) {
                Log::warning('[discord] webhook failed', ['status' => $status, 'body' => $res->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('[discord] webhook exception', ['msg' => $e->getMessage()]);
        }

        $this->record($title, $success, $status);
        return $success;
    }

    /**
     * Log each webhook call to discord_notifications so the admin dashboard
     * can surface activity. Silently swallows DB failures — notification
     * logging must never break the sending flow.
     */
    private function record(string $title, bool $success, ?int $status): void
    {
        try {
            DiscordNotification::create([
                'channel' => $this->channel,
                'title' => mb_substr($title, 0, 256),
                'success' => $success,
                'http_status' => $status,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // swallow — telemetry must not affect delivery
        }
    }
}
