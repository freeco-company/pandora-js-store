<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Services\Identity\PandoraCoreClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Phase 2 (ADR-007 / pandora-js-store#11)：把母艦既有 customers 灌進 platform，
 * 拿回 UUID v7 寫進 customers.pandora_user_uuid。
 *
 * 一次性命令，跑完 phase 2 backfill 即下台一鞠躬（不會排程）。
 *
 * 規模假設：< 10 真實客戶（ADR-007 §1）。腳本選擇 simple > clever：
 *   - 不分批（10 筆而已）
 *   - 不並行
 *   - dry-run 印 diff 給人看
 *
 * Usage:
 *   php artisan identity:backfill-to-platform --dry        # 列出會送哪些 customer
 *   php artisan identity:backfill-to-platform              # 真的送
 *   php artisan identity:backfill-to-platform --only=42    # 只跑某筆
 */
class IdentityBackfillToPlatformCmd extends Command
{
    protected $signature = 'identity:backfill-to-platform
        {--dry : 不送，只 list 會做什麼}
        {--only= : 只跑特定 customer_id}
        {--force : 即使已有 pandora_user_uuid 也重送（覆蓋）}';

    protected $description = 'Phase 2 一次性 backfill：母艦 customers → platform mirror endpoint，回填 pandora_user_uuid';

    public function handle(PandoraCoreClient $client): int
    {
        $query = Customer::query();

        if ($this->option('only')) {
            $query->where('id', (int) $this->option('only'));
        } else {
            // 只挑「真的需要」的：未填 uuid 且有可識別欄位（email or phone or oauth）
            if (! $this->option('force')) {
                $query->whereNull('pandora_user_uuid');
            }
            $query->where(function ($q) {
                $q->whereNotNull('email')
                    ->orWhereNotNull('phone')
                    ->orWhereNotNull('google_id')
                    ->orWhereNotNull('line_id');
            });
        }

        /** @var Collection<int, Customer> $customers */
        $customers = $query->orderBy('id')->get();

        if ($customers->isEmpty()) {
            $this->info('沒有需要 backfill 的 customer。');

            return self::SUCCESS;
        }

        $this->info("準備 backfill {$customers->count()} 筆 customer".
            ($this->option('dry') ? '（DRY RUN，不送）' : ''));

        $rows = [];
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $payload = $this->buildPayload($customer);

            $rows[] = [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'existing_uuid' => $customer->pandora_user_uuid ?? '(empty)',
                'identities' => count($payload['identities']),
            ];

            if ($this->option('dry')) {
                continue;
            }

            $response = $client->customerUpsert($payload);

            if ($response->success) {
                // platform 端 key 是 'group_user_id'；其餘為相容別名
                $uuid = $response->data['group_user_id']
                    ?? $response->data['uuid']
                    ?? $response->data['user']['uuid']
                    ?? null;
                if (! is_string($uuid) || $uuid === '') {
                    $this->error("Customer #{$customer->id}: platform 回 200 但沒給 uuid，data=".json_encode($response->data));
                    $failed++;

                    continue;
                }
                $customer->pandora_user_uuid = $uuid;
                $customer->saveQuietly();  // 避免 observer 把這個寫回 outbox 又繞一圈
                $sent++;
                $this->line("  ✓ #{$customer->id} → {$uuid}");
            } else {
                $this->error("Customer #{$customer->id}: HTTP {$response->status} — ".substr((string) $response->error, 0, 200));
                $failed++;
            }
        }

        $this->table(['customer_id', 'email', 'phone', 'existing_uuid', '#identities'], $rows);

        if (! $this->option('dry')) {
            $this->info("結果: sent={$sent} skipped={$skipped} failed={$failed}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{fp_customer_id: int, email_canonical: ?string, phone_canonical: ?string, display_name: ?string, identities: list<array{type: string, value: string, is_primary: bool}>}
     */
    private function buildPayload(Customer $customer): array
    {
        $identities = $customer->identities()->get()
            ->map(fn (CustomerIdentity $i) => [
                'type' => $this->mapIdentityType($i->type),
                'value' => (string) $i->value,
                'is_primary' => (bool) $i->is_primary,
            ])
            ->values()
            ->all();

        // 補：customers 表上的 google_id / line_id 若 customer_identities 沒同步到也送
        if ($customer->google_id && ! $this->hasIdentityValue($identities, 'google', (string) $customer->google_id)) {
            $identities[] = ['type' => 'google', 'value' => (string) $customer->google_id, 'is_primary' => true];
        }
        if ($customer->line_id && ! $this->hasIdentityValue($identities, 'line', (string) $customer->line_id)) {
            $identities[] = ['type' => 'line', 'value' => (string) $customer->line_id, 'is_primary' => true];
        }

        return [
            'fp_customer_id' => $customer->id,
            'email_canonical' => $customer->email,
            'phone_canonical' => $customer->phone,
            'display_name' => $customer->name,
            'identities' => $identities,
        ];
    }

    private function mapIdentityType(string $local): string
    {
        return match ($local) {
            'google_id' => 'google',
            'line_id' => 'line',
            'apple_id' => 'apple',
            default => $local,
        };
    }

    /**
     * @param  list<array{type: string, value: string, is_primary?: bool}>  $identities
     */
    private function hasIdentityValue(array $identities, string $type, string $value): bool
    {
        foreach ($identities as $i) {
            if ($i['type'] === $type && $i['value'] === $value) {
                return true;
            }
        }

        return false;
    }
}
