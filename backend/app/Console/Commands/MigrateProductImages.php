<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One-shot: pull every jerosse.com.tw-hosted image out of product descriptions,
 * download to /storage/products/{slug}/, and rewrite the description src to our domain.
 *
 * Why: we currently hotlink jerosse.com.tw — if they firewall or 404 we break.
 * After this migration the site is self-hosted for all product imagery.
 *
 * Safe to re-run; skips any image that's already been migrated (url starts with /storage/).
 */
class MigrateProductImages extends Command
{
    protected $signature = 'products:migrate-images
        {--product= : Migrate a single product by slug}
        {--dry-run : Report only, do not download or save}';

    protected $description = 'Download jerosse.com.tw images in product descriptions to local /storage/';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('product');

        $query = Product::query();
        if ($only) $query->where('slug', $only);

        $stats = ['scanned' => 0, 'downloaded' => 0, 'rewritten' => 0, 'errors' => 0];

        foreach ($query->cursor() as $product) {
            $stats['scanned']++;
            if (! $product->description) continue;

            $desc = $product->description;
            $changed = false;

            $desc = preg_replace_callback(
                '/<img([^>]*?)src="(https?:\/\/(?:www\.)?jerosse\.com\.tw\/[^"]+)"([^>]*)>/i',
                function ($m) use ($product, &$stats, $dryRun, &$changed) {
                    $before = $m[1];
                    $remoteUrl = $m[2];
                    $after = $m[3];

                    $localPath = $this->downloadImage($remoteUrl, $product->slug, $stats, $dryRun);

                    if ($localPath) {
                        $changed = true;
                        return "<img{$before}src=\"{$localPath}\"{$after}>";
                    }
                    return $m[0];       // leave original on failure
                },
                $desc,
            );

            if ($changed && ! $dryRun) {
                $product->description = $desc;
                $product->saveQuietly();
                $stats['rewritten']++;
            }
        }

        $this->table(
            ['Scanned', 'Downloaded', 'Rewritten', 'Errors'],
            [[$stats['scanned'], $stats['downloaded'], $stats['rewritten'], $stats['errors']]],
        );

        return self::SUCCESS;
    }

    private function downloadImage(string $url, string $slug, array &$stats, bool $dryRun): ?string
    {
        // De-dup: same-URL filename
        $pathPart = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION)) ?: 'jpg';
        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif']) ? $ext : 'jpg';

        $hash = substr(md5($url), 0, 8);
        $filename = "{$hash}.{$ext}";
        $relPath = "products/{$slug}/{$filename}";
        $storagePath = "public/{$relPath}";

        if (Storage::exists($storagePath)) {
            return "/storage/{$relPath}";
        }

        if ($dryRun) {
            $this->line("  [dry] would download: {$url}");
            return "/storage/{$relPath}";
        }

        try {
            $res = Http::withHeaders(['User-Agent' => 'PandoraBot/1.0'])
                ->timeout(30)->get($url);

            if (! $res->successful() || strlen($res->body()) < 100) {
                $stats['errors']++;
                $this->warn("  ✗ fetch failed ({$res->status()}): {$url}");
                return null;
            }

            Storage::put($storagePath, $res->body());
            $stats['downloaded']++;
            return "/storage/{$relPath}";
        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->warn("  ✗ {$e->getMessage()}: {$url}");
            return null;
        }
    }
}
