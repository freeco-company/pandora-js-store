# 婕樂纖仙女館 JEROSSE Store (pandora.js-store)

JEROSSE 官方正品授權經銷商 B2C 電商站。核心是「三階梯定價」：單件零售、組合價（≥2 件）、VIP 價（組合總額 ≥ 4000）。

## Stack

- **Backend**: Laravel 13 + Sanctum + Socialite + Filament 5 (PHP 8.3) — port 8000
- **Frontend**: Next.js 16 + React 19 + Tailwind 4 (App Router, SSR + ISR) — port 3000
- **Database**: MariaDB
- **Auth**: Google OAuth（會員）+ password（admin 專用，Filament `/admin`）
- **Payment**: ECPay（AioCheckOut V5，SHA256 callback 驗簽）
- **Legacy**: `/wp-source` 是資料來源（WooCommerce DB，靠 `ImportWpData` command 搬進來），不是執行中的站

> ⚠️ Next.js 16 與 React 19 有 breaking changes。寫前端程式前先看 `frontend/node_modules/next/dist/docs/` — 別套舊版 App Router 習慣。

## 3-tier pricing（核心商業邏輯）

由 `backend/app/Services/CartPricingService.php` 與 `frontend/src/lib/pricing.ts` 雙邊實作，必須一致。

| 階梯 | 條件 | 價格欄位 |
|---|---|---|
| `regular` | 總數量 = 1 | `products.price` |
| `combo` | 總數量 ≥ 2 | `products.combo_price` |
| `vip` | combo 小計 ≥ 4000 | `products.vip_price` |

**規則**：整車用同一個 tier，不跨品項混算。前端顯示 tier badge 與「升級提示」。

## 遊戲化層（芽芽 / sprout mascot）

零售電商差異化重點：不是錦上添花，是留存槓桿。觸發點：首購、第 N 次購、連續登入、首評、推薦朋友下單、消費額里程碑。

- `<Mascot />` — SVG 芽芽，3 階成長 × 3 種心情
- `useCelebrate()` — confetti + badge modal，靠 API 回傳 `_achievement` / `_achievements` / `_outfits` keys 觸發
- `useSerendipity()` — 非阻斷浮泡，15% 機率、20 小時 cooldown
- `<ActivationQuest />` — 首頁/帳號頁 3 步任務（瀏覽商品 / 首購 / 首評）
- `AchievementService::award()` — idempotent（`(customer_id, code)` unique 索引）
- 成就碼定義：`backend/app/Services/AchievementCatalog.php`

任何新端點若有「值得慶祝的動作」→ 加 award + 回傳 celebration keys。

## 關鍵目錄

- `backend/app/Http/Controllers/Api/` — 公開 API（products / cart / coupons / orders / payment / articles）
- `backend/app/Filament/Resources/` — 後台 CRUD
- `backend/app/Services/{CartPricingService,EcpayService}.php` — 定價、金流
- `backend/app/Console/Commands/{ImportWpData,ScrapeJerosseArticles,GenerateSitemap}.php`
- `frontend/src/app/` — App Router 頁面
- `frontend/src/lib/{api,pricing}.ts` — API 型別 + client-side 定價（對齊 backend）
- `frontend/src/components/{Cart,Auth}Provider.tsx` — 狀態（localStorage）

## Colors

- Primary `#9F6B3E`（品牌金棕）· BG `#e7d9cb` · Text `#b09070`
- Fonts: Noto Sans TC

## Dev

```bash
cd backend && php artisan serve --port=8000
cd frontend && npm run dev -- --port 3000
```

- 後端測試：`cd backend && php artisan test`（commit 前必須全綠）
- 前端型別：`cd frontend && npx tsc --noEmit`
- 前端 build：`cd frontend && npm run build`

## Deploy

push → `ssh freeco` → `git pull && php8.3 artisan migrate --force && config:cache && npm install && npm run build && pm2 reload`。前端 `package.json` 版本先 bump、`git tag vX.Y.Z`、GitHub release。

## Don'ts（硬性規則）

- **Don't change 3-tier thresholds**（`VIP_THRESHOLD = 4000`、qty≥2 觸發 combo）— 兩邊要同步改，任何一邊漏改會造成結帳金額對不上。
- **Don't add fields to checkout form** without asking — 輸入摩擦直接掉轉換。
- **Don't mock ECPay SHA256 in tests when the secret exists** — 用固定測試金鑰驗真正的 hash。
- **Don't import from `/wp-source`** 的 PHP — 它是 WordPress installation，不是函式庫；要取資料透過 `ImportWpData` command 的 DB connection。
- **Don't port MLM features from `fairysalebox`** — 那是 B2B 批次銷售工具，本站是 B2C。只搬商品目錄、定價階梯的「資料」與行動裝置 UI 樣式。
- **Don't amend commits or force-push** unless explicitly told. 新 commit 就好。
- **Don't create `commands/` `agents/` `skills/` here** — memory 系統已經是規則儲存。

## Admin

- `admin@freeco.cc` 是唯一 admin（password + Filament `/admin`）
- 會員用 Google OAuth，沒有 admin 權限
