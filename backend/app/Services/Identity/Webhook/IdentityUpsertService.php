<?php

namespace App\Services\Identity\Webhook;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 把 platform 來的 user.upserted payload 落地到 customers + customer_identities。
 *
 * 原則（hard constraint：對既有客戶無感）：
 *   - 既有非空欄位「不覆蓋」（地址、line_id、加盟資料、referral_code 等）
 *   - 只 fill：null 或從未設過的欄位、name (display_name)、email、phone
 *   - 永遠以 pandora_user_uuid 為 lookup key（platform 的 single source of truth）
 *   - identity row 用 (type, value) UNIQUE 上 upsert，不刪舊的 — 母艦保留更多
 *     歷史 identity（曾經的 email / 已停用的 LINE）給客服查詢
 *
 * Provider type 對應（platform → 母艦反向）：
 *   email → email
 *   phone → phone
 *   google → google_id
 *   line → line_id
 *   apple → apple_id（母艦既有 schema 有的話，沒有就先當 string 存）
 */
class IdentityUpsertService
{
    private const TYPE_REVERSE_MAP = [
        'email' => 'email',
        'phone' => 'phone',
        'google' => 'google_id',
        'line' => 'line_id',
        'apple' => 'apple_id',
    ];

    /**
     * @param  array<string, mixed>  $data  payload 中的 data 部分
     * @return ?Customer null = 跳過（不夠資訊建立 customer，例如 phone-only platform user 但 customers.email 必填）
     */
    public function upsert(array $data): ?Customer
    {
        $uuid = $this->extractRequiredString($data, 'uuid');

        return DB::transaction(function () use ($uuid, $data): ?Customer {
            /** @var ?Customer $customer */
            $customer = Customer::query()->where('pandora_user_uuid', $uuid)->first();

            if ($customer === null) {
                // 看能不能從 email / phone 找到既有 customer 並補 uuid
                $matchResult = $this->matchExistingByEmailOrPhone($data);

                if ($matchResult === 'conflict') {
                    // 同 email/phone 但已綁定不同 uuid — 不能覆寫，skip 等人類介入
                    return null;
                }

                $customer = $matchResult;
                if ($customer !== null && $customer->pandora_user_uuid === null) {
                    $customer->pandora_user_uuid = $uuid;
                }
            }

            if ($customer === null) {
                // 母艦 customers.email 是 NOT NULL — 沒 email 也沒 phone 的 platform user
                // （e.g. LINE-only 還沒填 profile）暫時不在母艦 mirror，等他們有 email 再進來
                if (empty($data['email_canonical']) && empty($data['phone_canonical'])) {
                    Log::info('[IdentityWebhook] skip create: no email/phone yet', ['uuid' => $uuid]);

                    return null;
                }
                $customer = new Customer;
                $customer->pandora_user_uuid = $uuid;
                // 母艦 customers.password 是 NOT NULL，但 cutover 後 login 走 platform，
                // 本地 password 永遠不會被驗證；設無人能猜到的隨機字串維持 schema 約束。
                $customer->password = bcrypt(Str::random(32));
            }

            $this->fillNonDestructive($customer, $data);
            $customer->save();

            $this->syncIdentities($customer, $data['identities'] ?? []);

            return $customer;
        });
    }

    /**
     * 不覆蓋既有非空欄位 — 母艦保留所有 PII / 加盟業務資料。
     *
     * @param  array<string, mixed>  $data
     */
    private function fillNonDestructive(Customer $customer, array $data): void
    {
        $updates = [
            'name' => $data['display_name'] ?? null,
            'email' => $data['email_canonical'] ?? null,
            'phone' => $data['phone_canonical'] ?? null,
        ];

        foreach ($updates as $field => $value) {
            if ($value === null) {
                continue;
            }
            // 只在欄位還沒有值時才寫入
            if (empty($customer->{$field})) {
                $customer->{$field} = $value;
            }
        }

        // google_id / line_id 從 identities 回填（母艦既有 schema 仍用這兩欄當 cache）
        foreach ($data['identities'] ?? [] as $i) {
            $type = $i['type'] ?? null;
            $value = $i['value'] ?? null;
            if ($type === 'google' && $value !== null && empty($customer->google_id)) {
                $customer->google_id = $value;
            }
            if ($type === 'line' && $value !== null && empty($customer->line_id)) {
                $customer->line_id = $value;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Customer|'conflict'|null Customer = match 到、'conflict' = email/phone 已綁不同 uuid（拒絕 silent overwrite）、null = 無 match
     */
    private function matchExistingByEmailOrPhone(array $data): Customer|string|null
    {
        $email = $data['email_canonical'] ?? null;
        $phone = $data['phone_canonical'] ?? null;
        $incomingUuid = $data['uuid'] ?? null;

        foreach (['email' => $email, 'phone' => $phone] as $field => $value) {
            if ($value === null) {
                continue;
            }
            $existing = Customer::query()->where($field, $value)->first();
            if ($existing === null) {
                continue;
            }
            if ($existing->pandora_user_uuid !== null && $existing->pandora_user_uuid !== $incomingUuid) {
                Log::warning('[IdentityWebhook] conflict: existing customer has different uuid', [
                    $field => $value,
                    'existing_uuid' => $existing->pandora_user_uuid,
                    'incoming_uuid' => $incomingUuid,
                ]);

                return 'conflict';
            }

            return $existing;
        }

        return null;
    }

    /**
     * @param  list<array{type: string, value: string, verified_at?: ?string, is_primary?: bool}>  $identities
     */
    private function syncIdentities(Customer $customer, array $identities): void
    {
        foreach ($identities as $identity) {
            $platformType = $identity['type'] ?? null;
            $value = $identity['value'] ?? null;
            if ($platformType === null || $value === null) {
                continue;
            }
            $localType = self::TYPE_REVERSE_MAP[$platformType] ?? $platformType;

            CustomerIdentity::query()->updateOrInsert(
                ['type' => $localType, 'value' => $value],
                [
                    'customer_id' => $customer->id,
                    'verified_at' => $identity['verified_at'] ?? null,
                    'is_primary' => (bool) ($identity['is_primary'] ?? false),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractRequiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Missing required field: {$key}");
        }

        return $value;
    }
}
