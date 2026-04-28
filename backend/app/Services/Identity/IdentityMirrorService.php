<?php

namespace App\Services\Identity;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\OutboxIdentityEvent;
use Illuminate\Support\Facades\Log;

/**
 * 將母艦 customer / customer_identity 變動序列化成 platform 預期格式，
 * 排入 outbox_identity_events 等 queue worker 送出。
 *
 * 用法：
 *   IdentityMirrorService::queueCustomerUpsert($customer);
 *   IdentityMirrorService::queueIdentityAdded($identity);
 *
 * Feature flag IDENTITY_MIRROR_ENABLED 控制是否啟用（預設 false）。
 *
 * Provider type 對應（母艦 → platform）：
 *   google_id → google
 *   line_id   → line
 *   email     → email
 *   phone     → phone
 *
 * shadow mode 原則：寫 outbox 失敗也吞掉，只 log warning，絕對不炸主流程。
 */
class IdentityMirrorService
{
    private const TYPE_MAP = [
        'google_id' => 'google',
        'line_id' => 'line',
        'email' => 'email',
        'phone' => 'phone',
    ];

    public static function isEnabled(): bool
    {
        return (bool) config('services.pandora_core.mirror_enabled', false);
    }

    public static function queueCustomerUpsert(Customer $customer): void
    {
        if (! self::isEnabled()) {
            return;
        }

        try {
            $identities = $customer->identities()->get()
                ->map(fn (CustomerIdentity $i) => [
                    'type' => self::TYPE_MAP[$i->type] ?? $i->type,
                    'value' => $i->value,
                    'is_primary' => (bool) $i->is_primary,
                ])->all();

            OutboxIdentityEvent::create([
                'event_type' => 'customer.upserted',
                'customer_id' => $customer->id,
                'payload' => [
                    'fp_customer_id' => $customer->id,
                    'email_canonical' => $customer->email,
                    'phone_canonical' => $customer->phone,
                    'display_name' => $customer->name,
                    'identities' => $identities,
                ],
                'status' => OutboxIdentityEvent::STATUS_PENDING,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[IdentityMirror] Failed to queue customer.upserted', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function queueIdentityAdded(CustomerIdentity $identity): void
    {
        if (! self::isEnabled()) {
            return;
        }

        // identity 變動其實也是 customer 整體 sync，平台端 upsert by (type, value)
        // 反查 customer 並丟整批進去（簡化 worker 邏輯，避免 race condition）
        try {
            $customer = Customer::find($identity->customer_id);
            if ($customer !== null) {
                self::queueCustomerUpsert($customer);
            }
        } catch (\Throwable $e) {
            Log::warning('[IdentityMirror] Failed to queue identity.added', [
                'identity_id' => $identity->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
