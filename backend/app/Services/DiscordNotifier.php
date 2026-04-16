<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Discord webhook client.
 * Configure via env: DISCORD_COMPLIANCE_WEBHOOK=https://discord.com/api/webhooks/...
 * No-op if env is missing (safe in dev/test).
 */
class DiscordNotifier
{
    public function __construct(private readonly ?string $webhook = null) {}

    public static function compliance(): self
    {
        return new self(config('services.discord.compliance_webhook'));
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
            if (! $res->successful()) {
                Log::warning('[discord] webhook failed', ['status' => $res->status(), 'body' => $res->body()]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('[discord] webhook exception', ['msg' => $e->getMessage()]);
            return false;
        }
    }
}
