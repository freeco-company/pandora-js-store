<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 把現有 customers 表的 4 個 identity 欄位回填到 customer_identities。
 *
 * 設計重點：
 *   - 跳過已存在的 (type, value) — 重跑安全
 *   - 衝突（同 value 但 customer_id 不一樣）只 log，不錯亂；那是 dedupe command 要處理
 *   - email 是 `@line.user` placeholder 的也會被建為 identity（因為它確實是該 customer 的 email），
 *     但 dedupe command 會用「placeholder vs real email」判斷合併方向
 *
 * 呼叫：php artisan customer:backfill-identities
 *       php artisan customer:backfill-identities --dry
 */
class BackfillCustomerIdentitiesCmd extends Command
{
    protected $signature = 'customer:backfill-identities {--dry : 只報告不寫入}';
    protected $description = 'Backfill existing customers into customer_identities table';

    private const FIELDS = [
        ['column' => 'email', 'type' => CustomerIdentity::TYPE_EMAIL],
        ['column' => 'phone', 'type' => CustomerIdentity::TYPE_PHONE],
        ['column' => 'google_id', 'type' => CustomerIdentity::TYPE_GOOGLE],
        ['column' => 'line_id', 'type' => CustomerIdentity::TYPE_LINE],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $stats = ['inserted' => 0, 'skipped_existing' => 0, 'skipped_conflict' => 0, 'scanned' => 0];

        Customer::query()->orderBy('id')->chunkById(500, function ($chunk) use (&$stats, $dry) {
            foreach ($chunk as $customer) {
                $stats['scanned']++;
                foreach (self::FIELDS as $f) {
                    $value = $customer->{$f['column']};
                    if (!$value) continue;

                    $existing = CustomerIdentity::where('type', $f['type'])
                        ->where('value', $value)
                        ->first();

                    if ($existing) {
                        if ($existing->customer_id === $customer->id) {
                            $stats['skipped_existing']++;
                        } else {
                            $stats['skipped_conflict']++;
                            $this->warn(sprintf(
                                'CONFLICT: %s=%s already owned by customer %d (current=%d)',
                                $f['type'], $value, $existing->customer_id, $customer->id
                            ));
                        }
                        continue;
                    }

                    if (!$dry) {
                        CustomerIdentity::create([
                            'customer_id' => $customer->id,
                            'type' => $f['type'],
                            'value' => $value,
                            'is_primary' => true,
                            'verified_at' => in_array($f['type'], [CustomerIdentity::TYPE_GOOGLE, CustomerIdentity::TYPE_LINE])
                                ? now() : null,
                        ]);
                    }
                    $stats['inserted']++;
                }
            }
        });

        $this->info(sprintf(
            '%s scanned=%d inserted=%d skipped_existing=%d skipped_conflict=%d',
            $dry ? '[DRY]' : '[LIVE]',
            $stats['scanned'], $stats['inserted'], $stats['skipped_existing'], $stats['skipped_conflict']
        ));

        return self::SUCCESS;
    }
}
