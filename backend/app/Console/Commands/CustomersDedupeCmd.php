<?php

namespace App\Console\Commands;

use App\Services\CustomerMergeService;
use Illuminate\Console\Command;

/**
 * 偵測重複 customer + 視 confidence 自動合併。
 *
 *   php artisan customers:dedupe                    # 預設：列報告 + 提示如何 auto-merge
 *   php artisan customers:dedupe --auto-high        # 自動合併 high confidence（phone+placeholder）
 *   php artisan customers:dedupe --json             # JSON 輸出（給 Filament UI / monitoring 用）
 */
class CustomersDedupeCmd extends Command
{
    protected $signature = 'customers:dedupe
        {--auto-high : 自動合併 high confidence 候選（phone match + placeholder email）}
        {--json : 以 JSON 格式輸出（不互動）}';

    protected $description = 'Detect and optionally merge duplicate customers';

    public function handle(CustomerMergeService $merger): int
    {
        $candidates = $merger->detectDuplicates();
        $high = array_values(array_filter($candidates, fn ($c) => $c['confidence'] === 'high'));
        $medium = array_values(array_filter($candidates, fn ($c) => $c['confidence'] === 'medium'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'high' => array_map(fn ($c) => $this->serializeCandidate($c), $high),
                'medium' => array_map(fn ($c) => $this->serializeCandidate($c), $medium),
                'high_count' => count($high),
                'medium_count' => count($medium),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info(sprintf('偵測完成：high=%d  medium=%d', count($high), count($medium)));

        if (count($high) > 0 && !$this->option('auto-high')) {
            $this->warn('使用 --auto-high 自動合併以下 high confidence 候選：');
            foreach ($high as $c) {
                $this->line(sprintf(
                    '  • #%d (%s) ↔ #%d (%s)  原因：%s',
                    $c['customer_a']->id, $c['customer_a']->email,
                    $c['customer_b']->id, $c['customer_b']->email,
                    $c['reason']
                ));
            }
        }

        if ($this->option('auto-high') && count($high) > 0) {
            $this->info('開始自動合併 high confidence 候選...');
            $merged = 0;
            $errors = 0;
            foreach ($high as $c) {
                try {
                    $picked = $merger->pickSurvivor($c['customer_a'], $c['customer_b']);
                    $stats = $merger->merge(
                        surviving: $picked['surviving'],
                        absorbed: $picked['absorbed'],
                        reason: 'auto:phone+placeholder',
                    );
                    $this->line(sprintf(
                        '  ✓ merged absorbed #%d → surviving #%d  (orders: %d, addresses: %d)',
                        $picked['absorbed']->id, $picked['surviving']->id,
                        $stats['orders'] ?? 0, $stats['customer_addresses'] ?? 0
                    ));
                    $merged++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error(sprintf(
                        '  ✗ #%d ↔ #%d failed: %s',
                        $c['customer_a']->id, $c['customer_b']->id, $e->getMessage()
                    ));
                }
            }
            $this->info("自動合併完成：merged=$merged  errors=$errors");
        }

        if (count($medium) > 0) {
            $this->warn('Medium confidence 候選需人工確認，請至 /admin/customer-merger 處理：');
            foreach ($medium as $c) {
                $this->line(sprintf(
                    '  • #%d (%s) ↔ #%d (%s)  原因：%s',
                    $c['customer_a']->id, $c['customer_a']->email,
                    $c['customer_b']->id, $c['customer_b']->email,
                    $c['reason']
                ));
            }
        }

        return self::SUCCESS;
    }

    private function serializeCandidate(array $c): array
    {
        return [
            'customer_a' => [
                'id' => $c['customer_a']->id,
                'email' => $c['customer_a']->email,
                'phone' => $c['customer_a']->phone,
                'name' => $c['customer_a']->name,
                'total_orders' => $c['customer_a']->total_orders,
            ],
            'customer_b' => [
                'id' => $c['customer_b']->id,
                'email' => $c['customer_b']->email,
                'phone' => $c['customer_b']->phone,
                'name' => $c['customer_b']->name,
                'total_orders' => $c['customer_b']->total_orders,
            ],
            'confidence' => $c['confidence'],
            'reason' => $c['reason'],
        ];
    }
}
