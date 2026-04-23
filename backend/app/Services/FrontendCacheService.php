<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Busts downstream caches (Next.js ISR + Cloudflare edge) when admin-editable
 * content changes. All calls are fire-and-forget with short timeouts — a
 * cache-purge failure must never block a Filament save.
 */
class FrontendCacheService
{
    /**
     * @param  string[]  $tags   Next.js fetch tags to revalidate
     * @param  string[]  $paths  App Router paths to revalidate (e.g. '/')
     */
    public function purge(array $tags = [], array $paths = []): void
    {
        $this->revalidateNext($tags, $paths);
        $this->purgeCloudflare($paths);
    }

    private function revalidateNext(array $tags, array $paths): void
    {
        $base = rtrim((string) config('services.frontend.url'), '/');
        $secret = (string) config('services.frontend.revalidate_secret');

        if ($base === '' || $secret === '' || (empty($tags) && empty($paths))) {
            return;
        }

        try {
            Http::timeout(3)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->post($base . '/api/revalidate', [
                    'tags' => array_values($tags),
                    'paths' => array_values($paths),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Next revalidate failed', [
                'tags' => $tags,
                'paths' => $paths,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function purgeCloudflare(array $paths): void
    {
        $zone = (string) config('services.cloudflare.zone_id');
        $token = (string) config('services.cloudflare.api_token');
        $base = rtrim((string) config('services.frontend.url'), '/');

        if ($zone === '' || $token === '' || $base === '' || empty($paths)) {
            return;
        }

        $files = array_values(array_map(
            fn (string $p) => $base . '/' . ltrim($p, '/'),
            $paths
        ));

        // Dedupe + also purge the bare domain for '/' entries so "/"
        // and "" both get invalidated.
        if (in_array($base . '/', $files, true)) {
            $files[] = $base;
        }
        $files = array_values(array_unique($files));

        try {
            Http::timeout(5)
                ->withToken($token)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
                    'files' => $files,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Cloudflare purge failed', [
                'files' => $files,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
