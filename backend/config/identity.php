<?php

/*
|--------------------------------------------------------------------------
| Identity Cutover (ADR-007 Phase 3 / pandora-js-store#12)
|--------------------------------------------------------------------------
|
| 母艦 OAuth login 路徑切換到 platform 的開關。
|
| Hard constraint：對既有客戶完全無感。所以提供三種模式 + whitelist + 失敗
| 自動 fallback 機制，搭配 4 道防線（feature flag / shadow / staging e2e /
| canary）才能上 prod。
|
| 模式：
|   - legacy   舊行為。母艦 AuthController 直接寫 customers，不打 platform
|   - shadow   舊行為仍是寫主路徑，但同時呼叫 platform 比對 uuid，diff 寫 log
|              用來在 prod 安全地驗證「platform 計算的 uuid 跟我們本地對得上」
|   - cutover  platform 是 source of truth：先呼 platform 拿 uuid，再用
|              IdentityUpsertService 同步到本地 customers。可選 whitelist 限定
|              特定客戶（canary 階段）。platform 失敗 → 自動 fallback 到 legacy
|              路徑（fail_open），避免任何一次 platform 故障就讓客戶登不進來
|
| Kill switch：把 IDENTITY_CUTOVER_MODE 改回 legacy 即時生效（不需重啟）。
*/

return [

    'cutover_mode' => env('IDENTITY_CUTOVER_MODE', 'legacy'),

    /*
     * Cutover 模式下若有設此 list（comma-separated email），只有名單內客戶走
     * platform 路徑；其餘走 legacy。空字串 = 全部走 platform（canary 結束後）。
     */
    'cutover_whitelist' => array_filter(array_map('trim', explode(',', (string) env('IDENTITY_CUTOVER_WHITELIST', '')))),

    /*
     * Cutover 模式下 platform 呼叫失敗時是否 fallback 到 legacy 路徑。
     * true = fail_open（推薦，對客戶無感）；false = fail_closed（login 失敗）
     */
    'cutover_fail_open' => (bool) env('IDENTITY_CUTOVER_FAIL_OPEN', true),

];
