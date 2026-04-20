<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow client — pings Bing + Yandex when URLs change so they re-crawl
 * without waiting for their regular schedule. Google ignores IndexNow.
 *
 * Fire-and-forget: failures are logged but never thrown. Disabled in
 * non-production unless INDEXNOW_ENABLED=true.
 *
 * Spec: https://www.indexnow.org/documentation
 */
class IndexNowService
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function __construct(
        private readonly string $key,
        private readonly string $host,
        private readonly bool $enabled,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.indexnow.key'),
            (string) config('services.indexnow.host'),
            (bool) config('services.indexnow.enabled'),
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->key !== '' && $this->host !== '';
    }

    /**
     * Submit up to 10,000 URLs in one call. URLs must all be on $this->host.
     *
     * @param  array<int, string>  $urls  Absolute URLs like https://host/path
     */
    public function submit(array $urls): bool
    {
        if (! $this->isEnabled() || empty($urls)) {
            return false;
        }

        $filtered = array_values(array_filter(array_unique($urls), function ($u) {
            return is_string($u)
                && str_starts_with($u, 'https://')
                && parse_url($u, PHP_URL_HOST) === $this->host;
        }));

        if (empty($filtered)) {
            return false;
        }

        try {
            $res = Http::timeout(8)->post(self::ENDPOINT, [
                'host' => $this->host,
                'key' => $this->key,
                'keyLocation' => "https://{$this->host}/{$this->key}.txt",
                'urlList' => array_slice($filtered, 0, 10000),
            ]);

            // 200/202 = accepted. 422 = invalid URLs. 403 = key file missing.
            if (! $res->successful()) {
                Log::warning('[indexnow] submit failed', [
                    'status' => $res->status(),
                    'body' => $res->body(),
                    'count' => count($filtered),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('[indexnow] exception', ['msg' => $e->getMessage()]);
            return false;
        }
    }

    /** Convenience: submit a single URL. */
    public function submitOne(string $url): bool
    {
        return $this->submit([$url]);
    }
}
