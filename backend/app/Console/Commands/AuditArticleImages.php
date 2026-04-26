<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Daily safety net for article images.
 *
 * Two responsibilities:
 *   1. External URLs (jerosse.com.tw / i0.wp.com / 任何外站) → 嘗試下載到本地，
 *      改寫 src 到 /storage/articles/<type>/recovered-<hash>.<ext>。落地後我們
 *      就不再仰賴外站。
 *   2. 死圖（外站 404 + Wayback 也救不回 / 本地檔案被誤刪）→ 把整個 <img>
 *      tag 從 content 移除，避免前台破圖 icon。
 *
 * 流程：每抓到一個 <img>，依序嘗試
 *   a. 直接抓 (jerosse 還活著但有些圖在)
 *   b. Wayback Machine 抓 (jerosse 已刪但 web.archive.org 還有)
 *   c. 都失敗 → 把整 <img> 拿掉
 *
 * 排程於 routes/console.php，每天 04:30 跑（compliance:audit 之前）。
 */
class AuditArticleImages extends Command
{
    protected $signature = 'articles:audit-images
        {--dry : Report only, do not download or modify}
        {--article= : Audit a single article by slug}
        {--no-wayback : Skip Wayback Machine fallback (faster)}';

    protected $description = '掃描文章內圖片：外站圖落地 / 死圖移除';

    private int $statScanned = 0;
    private int $statImgsTotal = 0;
    private int $statDownloaded = 0;
    private int $statWayback = 0;
    private int $statRemoved = 0;
    private int $statRewritten = 0;
    private int $statLocalMissing = 0;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $useWayback = ! $this->option('no-wayback');

        $query = Article::query();
        if ($slug = $this->option('article')) {
            $query->where('slug', $slug);
        }

        foreach ($query->cursor() as $article) {
            $this->statScanned++;
            if (! $article->content) continue;

            $changed = false;
            $newContent = preg_replace_callback(
                '/<img[^>]*?src=["\']([^"\']+)["\'][^>]*>/i',
                function ($m) use ($article, $useWayback, $dry, &$changed) {
                    $this->statImgsTotal++;
                    $tag = $m[0];
                    $src = $m[1];

                    // Local /storage/... — verify file exists; if missing, log + remove.
                    if (str_starts_with($src, '/storage/')) {
                        $rel = ltrim(substr($src, strlen('/storage/')), '/');
                        if (Storage::disk('public')->exists($rel)) {
                            return $tag;
                        }
                        $this->statLocalMissing++;
                        $this->warn("  [missing-local] {$article->slug}: {$src} → strip");
                        $changed = true;
                        return '';
                    }

                    // External URL — try download → rewrite. Otherwise strip.
                    $local = $this->tryDownload($src, $article->source_type ?: 'blog', $dry);
                    if (! $local && $useWayback) {
                        $local = $this->tryWayback($src, $article->source_type ?: 'blog', $dry);
                    }

                    if ($local) {
                        $changed = true;
                        return preg_replace(
                            '/src=["\'][^"\']+["\']/',
                            'src="' . $local . '"',
                            $tag,
                            1
                        );
                    }

                    $this->warn("  [dead] {$article->slug}: {$src} → strip");
                    $this->statRemoved++;
                    $changed = true;
                    return '';
                },
                $article->content
            );

            if ($changed && ! $dry) {
                $article->content = $newContent;
                $article->saveQuietly();
                $this->statRewritten++;
            }
        }

        $this->newLine();
        $this->table(
            ['Articles', 'Imgs', 'Downloaded', 'Wayback', 'Stripped (dead)', 'Stripped (local-missing)', 'Articles rewritten'],
            [[
                $this->statScanned,
                $this->statImgsTotal,
                $this->statDownloaded,
                $this->statWayback,
                $this->statRemoved,
                $this->statLocalMissing,
                $this->statRewritten,
            ]],
        );

        if ($this->statRemoved > 0 || $this->statLocalMissing > 0) {
            Log::warning('[articles:audit-images] dead images stripped', [
                'dead' => $this->statRemoved,
                'local_missing' => $this->statLocalMissing,
                'dry' => $dry,
            ]);
        }

        return self::SUCCESS;
    }

    private function tryDownload(string $url, string $sourceType, bool $dry): ?string
    {
        $localPath = $this->plannedLocalPath($url, $sourceType);
        if (Storage::disk('public')->exists($localPath)) {
            return '/storage/' . $localPath;
        }

        if ($dry) {
            $this->line("  [dry-download] {$url}");
            return null;
        }

        try {
            $res = Http::withHeaders(['User-Agent' => 'PandoraBot/1.0 (article-audit)'])
                ->timeout(20)->get($url);
            if (! $res->successful() || strlen($res->body()) < 100) {
                return null;
            }
            Storage::disk('public')->put($localPath, $res->body());
            $this->statDownloaded++;
            $this->line("  [download] {$url} → /storage/{$localPath}");
            return '/storage/' . $localPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Wayback Machine fallback. Free, rate-limited, sometimes slow.
     * Use the most recent snapshot available.
     */
    private function tryWayback(string $url, string $sourceType, bool $dry): ?string
    {
        try {
            $api = 'https://archive.org/wayback/available?url=' . urlencode($url);
            $meta = Http::timeout(10)->get($api);
            if (! $meta->successful()) return null;
            $snapshot = data_get($meta->json(), 'archived_snapshots.closest.url');
            if (! $snapshot) return null;

            // Wayback returns URLs like https://web.archive.org/web/20231104120000/<orig>.
            // Replace the path mode `/web/<ts>/` with `/web/<ts>id_/` to get the raw original
            // (without their HTML wrapper).
            $raw = preg_replace('#/web/(\d+)/#', '/web/$1id_/', $snapshot, 1);

            $localPath = $this->plannedLocalPath($url, $sourceType);
            if ($dry) {
                $this->line("  [dry-wayback] {$url} ← {$raw}");
                return null;
            }

            $res = Http::withHeaders(['User-Agent' => 'PandoraBot/1.0 (article-audit)'])
                ->timeout(30)->get($raw);
            if (! $res->successful() || strlen($res->body()) < 100) {
                return null;
            }
            Storage::disk('public')->put($localPath, $res->body());
            $this->statWayback++;
            $this->line("  [wayback] {$url} → /storage/{$localPath}");
            return '/storage/' . $localPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function plannedLocalPath(string $url, string $sourceType): string
    {
        $pathPart = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($pathPart, PATHINFO_EXTENSION)) ?: 'jpg';
        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif']) ? $ext : 'jpg';
        $hash = substr(md5($url), 0, 10);
        return "articles/{$sourceType}/recovered-{$hash}.{$ext}";
    }
}
