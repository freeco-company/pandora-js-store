<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Services\Identity\IdentityMirrorService;
use Illuminate\Support\Facades\Log;

/**
 * 把 customers 表的 4 個 identity 欄位（email/phone/google_id/line_id）
 * 映射到 customer_identities 表，作為 lookup index。
 *
 * 寫入時機：
 *   - created：把現有 4 個欄位的非空值都建為 identity（is_primary=true）
 *   - updated：對改動的欄位，把舊值降級（is_primary=false）/ 新值建為 primary
 *
 * 故意保留多個 identity row，不刪舊的：
 *   - 客人可能 Google 換 email，舊 email 還是「他擁有的身分」之一，dedupe 時用得到
 *   - LINE 換 line_id 不可能（OAuth 永久 ID），但 email upgrade 後保留舊 placeholder 有歷史價值
 *
 * 例外：phone 改了，就把舊 phone 砍掉，因為 phone 不像 email 是有意義的「擁有過的身分」。
 *
 * 失敗策略：unique 衝突（identity 已被別 customer 佔用）會 log 並跳過，不
 * 阻擋 customer 儲存——這個情況代表資料已經有問題，需要走 dedupe 流程修。
 */
class CustomerObserver
{
    private const TYPE_MAP = [
        'email' => CustomerIdentity::TYPE_EMAIL,
        'phone' => CustomerIdentity::TYPE_PHONE,
        'google_id' => CustomerIdentity::TYPE_GOOGLE,
        'line_id' => CustomerIdentity::TYPE_LINE,
    ];

    public function created(Customer $customer): void
    {
        foreach (self::TYPE_MAP as $column => $type) {
            $value = $customer->{$column};
            if ($value) {
                $this->upsertIdentity($customer->id, $type, $value, isPrimary: true);
            }
        }

        // ADR-001 Step 1: 鏡寫到 Pandora Core (shadow mode, fire-and-forget)
        IdentityMirrorService::queueCustomerUpsert($customer);
    }

    public function updated(Customer $customer): void
    {
        $identityChanged = false;
        foreach (self::TYPE_MAP as $column => $type) {
            if (!$customer->wasChanged($column)) continue;
            $identityChanged = true;

            $newValue = $customer->{$column};
            $oldValue = $customer->getOriginal($column);

            // 舊值處理：phone 砍掉，其他降級保留
            if ($oldValue) {
                if ($type === CustomerIdentity::TYPE_PHONE) {
                    CustomerIdentity::where('customer_id', $customer->id)
                        ->where('type', $type)
                        ->where('value', $oldValue)
                        ->delete();
                } else {
                    CustomerIdentity::where('customer_id', $customer->id)
                        ->where('type', $type)
                        ->where('value', $oldValue)
                        ->update(['is_primary' => false]);
                }
            }

            // 新值寫入
            if ($newValue) {
                $this->upsertIdentity($customer->id, $type, $newValue, isPrimary: true);
            }
        }

        // ADR-001 Step 1: 鏡寫到 Pandora Core (只在 identity / 通用 profile 有變動時)
        $profileColumns = ['name', 'email', 'phone', 'google_id', 'line_id'];
        if ($identityChanged || $customer->wasChanged($profileColumns)) {
            IdentityMirrorService::queueCustomerUpsert($customer);
        }
    }

    /**
     * @return bool 是否成功（false = unique 衝突）
     */
    private function upsertIdentity(int $customerId, string $type, string $value, bool $isPrimary): bool
    {
        try {
            $existing = CustomerIdentity::where('type', $type)->where('value', $value)->first();

            if ($existing) {
                if ($existing->customer_id !== $customerId) {
                    // 同 (type,value) 已被別 customer 佔用 — 資料衝突，需 dedupe 介入
                    Log::warning('[customer.identity] (type,value) already owned by another customer', [
                        'type' => $type,
                        'value' => $value,
                        'wanted_customer' => $customerId,
                        'actual_customer' => $existing->customer_id,
                    ]);
                    return false;
                }
                // 同 customer 重複呼叫 → 升回 primary 即可
                $existing->update(['is_primary' => $isPrimary]);
                return true;
            }

            CustomerIdentity::create([
                'customer_id' => $customerId,
                'type' => $type,
                'value' => $value,
                'is_primary' => $isPrimary,
                // OAuth-derived (google_id / line_id) 視為已驗證；
                // email / phone 預設 null，未來加 verification 時再填
                'verified_at' => in_array($type, [CustomerIdentity::TYPE_GOOGLE, CustomerIdentity::TYPE_LINE])
                    ? now() : null,
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[customer.identity] upsert failed', [
                'type' => $type, 'msg' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
