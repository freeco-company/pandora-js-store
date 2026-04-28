<?php

namespace App\Services\Identity\Cutover;

/**
 * 決定一次 OAuth 流程要走 legacy 還是 platform 路徑（Phase 3 / pandora-js-store#12）。
 *
 * 設計原則：
 *   - 三模式 strict enum：legacy / shadow / cutover；其他值 fall back 到 legacy
 *     （avoid typo 把 prod 推進 cutover）
 *   - whitelist 只在 cutover 模式有意義，shadow 永遠對所有人 shadow（取得最大樣本）
 *   - 任何配置讀取錯誤、null、empty → 默認 legacy（fail-safe）
 */
class CutoverGate
{
    public const MODE_LEGACY = 'legacy';

    public const MODE_SHADOW = 'shadow';

    public const MODE_CUTOVER = 'cutover';

    private const VALID_MODES = [self::MODE_LEGACY, self::MODE_SHADOW, self::MODE_CUTOVER];

    public function mode(): string
    {
        $raw = (string) config('identity.cutover_mode', self::MODE_LEGACY);

        return in_array($raw, self::VALID_MODES, true) ? $raw : self::MODE_LEGACY;
    }

    /**
     * 決定這個 user（用 email 識別，因為 cutover 階段我們還沒有 platform uuid）
     * 是否走 platform 路徑作為「主寫入」。
     *
     * legacy / shadow → 永遠回 false（platform 不是主寫）
     * cutover         → 看 whitelist
     */
    public function shouldUsePlatformAsMaster(?string $email): bool
    {
        if ($this->mode() !== self::MODE_CUTOVER) {
            return false;
        }

        /** @var list<string> $whitelist */
        $whitelist = config('identity.cutover_whitelist', []);
        if ($whitelist === []) {
            return true;  // 全開
        }

        return $email !== null && in_array(strtolower($email), array_map('strtolower', $whitelist), true);
    }

    /**
     * shadow mode 才需要對 platform 跑「次寫入 + 比對」。
     */
    public function shouldShadow(): bool
    {
        return $this->mode() === self::MODE_SHADOW;
    }

    public function failOpen(): bool
    {
        $value = config('identity.cutover_fail_open');

        // null（未設）→ 默認 true（fail-open，對客戶無感）；明確設 false 才 fail-closed
        return $value === null ? true : (bool) $value;
    }
}
