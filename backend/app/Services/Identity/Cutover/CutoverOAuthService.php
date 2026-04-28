<?php

namespace App\Services\Identity\Cutover;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AuthController 的 OAuth callback 走這支 service 統一處理 legacy / shadow /
 * cutover 三模式。回傳「現在要登入的 Customer」— controller 拿到後照舊發
 * sanctum token + 導頁。
 *
 * 設計重點（hard constraint：對既有客戶無感）：
 *   - 任一模式都「保證」回傳 Customer（platform 失敗自動 fallback）
 *   - shadow 模式：legacy 是主寫入；platform 跑在 try/catch 裡 ─ 即使爆炸也
 *     不影響登入流程
 *   - cutover 模式：先呼 platform 拿 uuid，把它寫到 customers.pandora_user_uuid
 *     上，但 customer 本身是用 legacy 的 findByIdentity 找/建（避免 race condition
 *     + 保留原 referral_code 等 booted hook 副作用）
 */
class CutoverOAuthService
{
    public function __construct(
        private CutoverGate $gate,
        private PlatformOAuthBridge $bridge,
    ) {}

    /**
     * @param  'google'|'line'|'apple'  $provider
     */
    public function loginOrCreate(
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $name,
        ?string $phone = null,
    ): Customer {
        $localProviderField = $this->localProviderField($provider);

        // Step 1: 用既有 findByIdentity 路徑找到 / 建立 customer（legacy 行為）
        $customer = $this->findOrCreateLocal($provider, $localProviderField, $providerUserId, $email, $name);

        // Step 2: 視模式決定要不要呼 platform 並把 uuid 寫回
        $mode = $this->gate->mode();

        if ($mode === CutoverGate::MODE_LEGACY) {
            return $customer;
        }

        // shadow 與 cutover 都會呼 platform；差別在 customer 是否已存在
        // （shadow 已存在、cutover 也已存在 — 我們已經先 findOrCreate）
        $usePlatform = $mode === CutoverGate::MODE_SHADOW
            || $this->gate->shouldUsePlatformAsMaster($email);

        if (! $usePlatform) {
            return $customer;
        }

        $uuid = $this->bridge->syncFromOAuth($provider, $providerUserId, $email, $name, $phone);

        if ($uuid === null) {
            // platform 失敗
            if ($mode === CutoverGate::MODE_SHADOW) {
                // shadow 不關心成敗；log 已在 bridge 端寫了
                return $customer;
            }
            // cutover：fail_open（默認）→ 用 legacy customer 登入；fail_closed → 拋
            if (! $this->gate->failOpen()) {
                throw new \RuntimeException('platform OAuth sync failed and fail_closed is set');
            }
            Log::warning('[Cutover] platform sync failed, falling back to legacy', [
                'provider' => $provider,
                'customer_id' => $customer->id,
            ]);

            return $customer;
        }

        // 成功拿到 uuid。記在本地 customer 上（idempotent；只在尚未綁時寫）
        if (empty($customer->pandora_user_uuid)) {
            $customer->pandora_user_uuid = $uuid;
            $customer->saveQuietly();
        } elseif ($customer->pandora_user_uuid !== $uuid) {
            // 嚴重 conflict：本地已綁的 uuid 跟 platform 算出來的不一樣
            // 不覆寫（避免破壞既有 customer），只 log + alert
            Log::error('[Cutover] uuid mismatch — local vs platform', [
                'customer_id' => $customer->id,
                'local_uuid' => $customer->pandora_user_uuid,
                'platform_uuid' => $uuid,
                'mode' => $mode,
            ]);
            // shadow 模式：純 log；cutover 模式還是用 legacy customer 不報錯
        } elseif ($mode === CutoverGate::MODE_SHADOW) {
            Log::info('[Cutover/shadow] uuid match', [
                'customer_id' => $customer->id,
                'uuid' => $uuid,
            ]);
        }

        return $customer;
    }

    /**
     * 既有 AuthController 的 find/create 邏輯抽出來；保留行為原汁原味。
     */
    private function findOrCreateLocal(
        string $provider,
        string $localProviderField,
        string $providerUserId,
        ?string $email,
        ?string $name,
    ): Customer {
        $customer = Customer::findByIdentity($localProviderField, $providerUserId);

        if (! $customer && $email) {
            $customer = Customer::findByIdentity('email', $email);
            if ($customer) {
                $updates = [$localProviderField => $providerUserId];
                if (! $customer->name && $name) {
                    $updates['name'] = $name;
                }
                $customer->update($updates);
            }
        }

        if (! $customer) {
            $customer = Customer::create([
                $localProviderField => $providerUserId,
                'name' => $name,
                // LINE email 可能 null；維持既有 fallback：lineId@line.user
                'email' => $email ?? ($provider === 'line' ? $providerUserId.'@line.user' : null),
                'membership_level' => 'regular',
                'password' => bcrypt(Str::random(32)),
            ]);
        }

        return $customer;
    }

    private function localProviderField(string $platformProvider): string
    {
        return match ($platformProvider) {
            'google' => 'google_id',
            'line' => 'line_id',
            'apple' => 'apple_id',
            default => $platformProvider,
        };
    }
}
