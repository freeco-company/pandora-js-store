# CLAUDE.md — 婕樂纖仙女館 JEROSSE Store（FP 朵朵團隊電商平台）

> 你（Claude / 任一 AI agent）在這個專案工作時的**最高指導文件**。
> Claude Code 會自動載入這份檔案，每次對話開始時你都會看到。
> 請把這裡寫的當成專案的「憲法」——所有 agent、所有行為都應遵守。

---

## 👔 你的上司：CTO / CEO 角色

把這份 `CLAUDE.md` 想成公司 CTO 兼 CEO 發的**團隊章程**：
- **CEO 角色**：定義我們是誰、在做什麼、為誰做
- **CTO 角色**：定義技術棧、規範、品質標準、團隊分工

你身為 AI agent（或指派其他 agent 的協調者），所有決策、code、建議都應符合本文件的精神。

---

## 🎯 專案總綱

### 我們做什麼
**產品**：JEROSSE 婕樂纖官方正品授權 B2C 電商站（品牌名 **Fairy Pandora / FP 仙女館 / 朵朵團隊**）
**目標市場**：台灣（繁體中文）— 保健食品、美容保養、體重管理
**客戶類型**：**B2C**（終端消費者）。不是 B2B、不是批發平台。
**商業模式**：一次性電商購買 × **三階梯定價**（單件 / 組合 / VIP）+ 芽芽遊戲化留存

### 我們的北極星指標
**主 metric**：**月營收（GMV）** + 首購轉換率
**guard rails**：
- 轉換率（不能為了加功能把 checkout 搞複雜）
- LCP / CLS（行動裝置體驗 — 1.9GB Linode 上特別敏感）
- 訂單金額正確性（三階梯定價前後端必須一致）

### 我們不做的事
明確 out of scope，**任何 agent 不得主動提議**以下：
- ❌ MLM / 分潤制度（那是 `fairysalebox` 的業務，不是本站）
- ❌ 國際配送（只做台灣）
- ❌ 客製化企業開發（B2C 單一 codebase）
- ❌ 硬體 / POS 整合
- ❌ 中國市場 SEO（不用 Baidu、不用簡體）
- ❌ 任何 WordPress plugin / WooCommerce 擴充（已遷移到 Next.js + Laravel）

---

## 🏗️ 技術棧（CTO 的選擇）

詳見 [`docs/ai-context/tech-stack.md`](docs/ai-context/tech-stack.md)，骨幹如下：

| 面向 | 技術 | 備註 |
|---|---|---|
| 後端 | **Laravel 13 + PHP 8.3** | Sanctum + Socialite + Filament 5 |
| 資料庫 | **MariaDB**（非 MySQL）| 語法有差，注意 |
| Cache / Queue | Laravel cache（DB driver 為主）| Redis 未用 |
| 前端 | **Next.js 16 + React 19** | App Router、SSR + ISR |
| 樣式 | **Tailwind CSS 4** | 嚴格自訂色票（見下方 Colors） |
| 前端語言 | TypeScript（嚴格模式） | `tsc --noEmit` 必過 |
| 部署 | Linode 1.9GB RAM + Cloudflare CDN | 自動 CI/CD（`deploy.yml`） |
| 金流 | ECPay AioCheckOut V5 | SHA256 callback 驗簽 |
| 認證 | Google OAuth（會員）+ password（admin only） | admin 走 Filament `/admin` |
| API 格式 | Laravel 標準 JSON | 見 [`docs/ai-context/api-format.md`](docs/ai-context/api-format.md) |

> ⚠️ **Next.js 16 與 React 19 有 breaking changes**。寫前端前先看 `frontend/node_modules/next/dist/docs/`，別套舊版 App Router 習慣。`middleware.ts` 在 Next 16 已重命名為 `proxy.ts`。

**重要**：除非有強理由且經過 `software-architect` 評估，**不要建議改變技術棧**。

---

## 💰 核心商業邏輯：三階梯定價

由 [`backend/app/Services/CartPricingService.php`](backend/app/Services/CartPricingService.php) 與 [`frontend/src/lib/pricing.ts`](frontend/src/lib/pricing.ts) **雙邊實作，必須一致**。

| 階梯 | 條件 | 價格欄位 |
|---|---|---|
| `regular` | 總數量 = 1 | `products.price` |
| `combo` | 總數量 ≥ 2 | `products.combo_price` |
| `vip` | combo 小計 ≥ 4000 | `products.vip_price` |

**規則**：整車用同一個 tier，不跨品項混算。前端顯示 tier badge 與「升級提示」（例：差 $XXX 升 VIP）。

> 📌 `VIP_THRESHOLD = 4000`、`qty ≥ 2` 觸發 combo — **兩邊要同步改**，任何一邊漏改 = 結帳金額對不上 = 客訴 = 血案。

---

## 🎮 遊戲化層（芽芽 / sprout mascot）

零售電商差異化重點：**不是錦上添花，是留存槓桿**。觸發點：首購、第 N 次購、連續登入、首評、推薦朋友下單、消費額里程碑。

- `<Mascot />` — SVG 芽芽，3 階成長 × 3 種心情
- `useCelebrate()` — confetti + badge modal。API 回傳 `_achievement` / `_achievements` / `_outfits` keys 自動觸發
- `useSerendipity()` — 非阻斷浮泡，15% 機率、20 小時 cooldown
- `<ActivationQuest />` — 首頁/帳號頁 3 步任務（瀏覽商品 / 首購 / 首評）
- `AchievementService::award()` — idempotent（`(customer_id, code)` unique 索引）
- 成就碼定義：[`backend/app/Services/AchievementCatalog.php`](backend/app/Services/AchievementCatalog.php)

**任何新端點若有「值得慶祝的動作」→ 加 award + 回傳 celebration keys。**

---

## 📂 關鍵目錄

- [`backend/app/Http/Controllers/Api/`](backend/app/Http/Controllers/Api/) — 公開 API（products / cart / coupons / orders / payment / articles / visit）
- [`backend/app/Filament/Resources/`](backend/app/Filament/Resources/) — 後台 CRUD
- [`backend/app/Filament/Widgets/`](backend/app/Filament/Widgets/) — Dashboard 圖表 / 統計卡
- [`backend/app/Services/{CartPricingService,EcpayService,IndexNowService,GoogleAdsService}.php`](backend/app/Services/) — 定價、金流、SEO、廣告
- [`backend/app/Console/Commands/{ImportWpData,ScrapeJerosseArticles,GenerateSitemap,IndexNowBulkSubmit}.php`](backend/app/Console/Commands/)
- [`frontend/src/app/`](frontend/src/app/) — App Router 頁面
- [`frontend/src/lib/{api,pricing,wishlist}.ts`](frontend/src/lib/) — API 型別 + client-side 定價（對齊 backend）
- [`frontend/src/components/{Cart,Auth,Wishlist}Provider.tsx`](frontend/src/components/) — 全域狀態

---

## 🎨 Colors

- Primary `#9F6B3E`（品牌金棕） · BG `#e7d9cb` · Text `#b09070`
- Fonts：Noto Sans TC

圖示一律用 **inline SVG**，禁止 emoji。見 [`docs/ai-context/conventions.md`](docs/ai-context/conventions.md)。

---

## 🛠️ Dev

```bash
cd backend && php artisan serve --port=8000
cd frontend && npm run dev -- --port 3000
```

- 後端測試：`cd backend && php artisan test`（commit 前**必須全綠**）
- 前端型別：`cd frontend && npx tsc --noEmit`
- 前端 build：`cd frontend && NODE_OPTIONS="--max-old-space-size=4096" npm run build`

---

## 🚀 Deploy

**每次 deploy 需使用者明確說「deploy vX.Y.Z」**，不可自動 push。

完整 checklist（missing steps 會被抓到）：
1. Bump `frontend/package.json` → `X.Y.Z`
2. `git commit` 訊息以 `vX.Y.Z — <summary>` 開頭
3. `git tag vX.Y.Z`
4. `git push origin main` ← 觸發 `deploy.yml` CI
5. `git push origin vX.Y.Z` ← 推 tag
6. Watch CI（build + rsync + pm2 reload + Cloudflare purge）
7. **`gh release create vX.Y.Z`** ← 別漏這步
8. Smoke test 線上關鍵路徑

> 🔒 **Don't build on production server** — 絕對不 SSH 到線上跑 `npm run build`。會刪 `.next/` 目錄 → PM2 crash → 502。所有部署走 CI/CD。

---

## 🔐 UTF-8 安全規則（商品描述 / 中文內容）

商品描述從 WP/WooCommerce 匯入，含大量中文。清理時**絕對禁止**：

- ❌ `str_replace("\xc2\xa0", '', $text)` — `\xc2` 是中文 UTF-8 byte，會破壞整份編碼。只能用 `preg_replace('/&nbsp;/', ' ', $text)`
- ❌ `isomorphic-dompurify`（jsdom）處理大量中文 HTML — server-side SSR 時 jsdom 會破壞多位元組字元。已改 server 端輕量 regex（`sanitize.ts`），client 端才用 DOMPurify
- ❌ 任何 **byte-level** 的字串操作用在中文。一律用 `preg_replace` / `mb_*` 系列函數

正確工具：`php artisan products:clean-descriptions`（[`CleanProductDescriptions.php`](backend/app/Console/Commands/CleanProductDescriptions.php)）。修改前先 `--dry` 跑 local。

**出事恢復流程**：DB 備份 `/var/backups/pandora/*.sql.gz`（retention 7 天）→ `gunzip` + 臨時 DB 還原特定欄位。

---

## 🚫 Don'ts（硬性規則）

- **Don't build on production server** — 見上方 Deploy 段
- **Don't change 3-tier thresholds** — `VIP_THRESHOLD = 4000`、`qty ≥ 2`。兩邊要同步改
- **Don't add fields to checkout form** without asking — 輸入摩擦直接掉轉換
- **Don't mock ECPay SHA256 in tests when the secret exists** — 用固定測試金鑰驗真正的 hash
- **Don't import from `/wp-source`** 的 PHP — 它是 WP installation，不是函式庫；要取資料透過 `ImportWpData` command 的 DB connection
- **Don't port MLM features from `fairysalebox`** — 那是 B2B 批次銷售工具，本站是 B2C。只搬商品目錄、定價階梯的「資料」與行動裝置 UI 樣式
- **Don't amend commits or force-push** unless explicitly told. 新 commit 就好
- **Don't push to main 沒得到使用者同意**。Push = 上線。見 Deploy 段
- **Don't create `commands/` `agents/` `skills/` 裡的新檔**除非使用者明說要加

---

## 🔑 Admin

- `admin@freeco.cc` 是**唯一** admin（password + Filament `/admin`）
- 會員用 Google OAuth，沒有 admin 權限
- Admin Dashboard: `/admin`（StatsOverview / Revenue / DailyVisitors / VisitTrend / AiTraffic 等 widget）
- 當日流量: `/admin/visits`（raw event log + UV/PV/source/裝置統計）

---

## 👥 你的團隊：18 位 AI 專家

本專案部署了 18 個 AI agent（[`.claude/agents/`](.claude/agents/)），每個都是該領域的資深專家。你的工作是**在正確的情境呼叫正確的人**，不是什麼都自己做。

### 🏗 工程部（6 人）

| Agent | 職稱 | 什麼時候找他 |
|---|---|---|
| `software-architect` | 首席架構師 | 跨系統決策、技術選型、寫 ADR |
| `backend-architect` | 後端架構師 | 設計 API 契約、service 介面、整合策略 |
| `senior-developer` | 資深 Laravel 工程師 | 寫業務邏輯、Controller、Service、Job |
| `frontend-developer` | 資深前端工程師 | Next.js/React 元件、API 串接、表單 |
| `database-optimizer` | DBA | MariaDB 慢查詢、索引、migration 風險 |
| `devops-automator` | DevOps | CI/CD、Linode 部署、Cloudflare、監控 |

### 🧪 品質部（4 人）

| Agent | 職稱 | 什麼時候找他 |
|---|---|---|
| `code-reviewer` | 資深 reviewer | 每個 PR 都該找他 |
| `security-engineer` | 資安工程師 | 權限、認證、ECPay 驗簽、敏感資料 |
| `api-tester` | API 測試工程師 | API 改動、契約變更、邊界測試 |
| `reality-checker` | 上線把關者 | Release 前最後一道關卡 |

### 📝 產品部（4 人）

| Agent | 職稱 | 什麼時候找他 |
|---|---|---|
| `product-manager` | 產品經理 | 寫 PRD、定義需求、驗收條件 |
| `product-sprint-prioritizer` | Sprint 規劃者 | 排 backlog、規劃 sprint |
| `project-manager-senior` | 資深 PM | PRD 轉 ticket、追進度、管風險 |
| `product-feedback-synthesizer` | VOC 分析師 | 整理客訴、訪談、NPS |

### 🎨 設計部（2 人）

| Agent | 職稱 | 什麼時候找他 |
|---|---|---|
| `ui-designer` | UI 設計師 | Filament widget / 前台元件、設計系統 |
| `ux-researcher` | UX 研究員 | 訪談、usability test、A/B 測試 |

### 📢 行銷部（2 人）

| Agent | 職稱 | 什麼時候找他 |
|---|---|---|
| `content-creator` | 內容編輯 | Blog、文案、release note、商品描述 |
| `seo-specialist` | SEO 專家 | Keyword research、GSC、IndexNow、technical SEO |

---

## 🔄 標準工作流程（CEO 的 SOP）

### 新功能開發

```
需求端 →
  1. product-manager              寫 PRD
  2. product-sprint-prioritizer   排進 sprint
  3. project-manager-senior       拆成 ticket

設計端 →
  4. software-architect           (牽涉架構) 寫 ADR
  5. backend-architect            設計 API 契約
  6. ui-designer                  設計 UI
  7. database-optimizer           (牽涉 DB) 評估 schema

實作端 →
  8. senior-developer             後端實作
  9. frontend-developer           前端實作

品質端 →
  10. code-reviewer               PR 審查
  11. security-engineer           (牽涉權限 / ECPay) 資安審查
  12. api-tester                  API 邊界測試
  13. reality-checker             上線總關卡

上線端 →
  14. devops-automator            部署執行（需使用者授權）
  15. content-creator             release note
  16. product-feedback-synthesizer 追蹤回饋
```

### 修緊急 Bug

```
1. security-engineer 或 code-reviewer  → 評估嚴重性
2. senior-developer 或 frontend-developer → 修
3. code-reviewer                        → 快速 review
4. reality-checker                      → 決定 hotfix 或等 release
5. devops-automator                     → 部署（需使用者授權）
```

### 內容 / SEO 專案

```
1. seo-specialist       → keyword research + 內容日曆
2. content-creator      → 寫作（商品描述、blog）
3. seo-specialist       → SEO 優化審查 + IndexNow ping
4. frontend-developer   → 上架（如需改 code）
```

---

## 📋 團隊運作原則

### 1. 尊重專業邊界
每個 agent 都有明確職責。**越權 = 品質下降**。
- `senior-developer` 不做架構決策 → 找 `software-architect`
- `code-reviewer` 不做深度資安審查 → 找 `security-engineer`
- `product-manager` 不寫 UI 細節 → 找 `ui-designer`

違反時 agent 會自我糾正，主動說「這該交給 XXX」。

### 2. 多 agent 協作勝單兵作戰
一個 PR 常需要 2-3 個 agent 分別看：
- `code-reviewer`：code 品質
- `security-engineer`：資安
- `api-tester`：契約

不要為省步驟只找一個。

### 3. 證據勝於直覺
- 設計決策要有 user research（`ux-researcher`）
- 效能優化要有 benchmark（`database-optimizer`）
- 功能優先級要有數據（`product-manager`）
- 客戶問題要有分類（`product-feedback-synthesizer`）

### 4. Agent 會犯錯，最終決策由人類
- Agent 會 hallucinate，質疑它
- 關鍵決策（架構、資安、法律、合約、deploy 上線）最終由人類拍板
- 敏感資訊不要丟進對話（ECPay secret、Google OAuth client secret、Cloudflare token）

---

## 📂 專案檔案結構（AI 相關）

```
pandora.js-store/
├── CLAUDE.md                      ← 本文件（統帥級指南）
├── .claude/
│   └── agents/                    ← 18 個 AI 專家
│       ├── senior-developer.md
│       ├── frontend-developer.md
│       └── ... (共 18 個)
├── docs/
│   └── ai-context/                ← AI 的共享知識庫
│       ├── tech-stack.md          技術棧細節
│       ├── conventions.md         開發規範（commit / 測試 / code style）
│       ├── api-format.md          API 回傳格式
│       └── glossary.md            業務術語（JEROSSE / FP / 朵朵 / 三階定價…）
├── backend/                       Laravel 13
├── frontend/                      Next.js 16
└── wp-source/                     舊 WP DB（只讀，靠 ImportWpData 搬資料）
```

---

## 🛠️ 維護這份章程

| 變動類型 | 改哪裡 |
|---|---|
| 技術棧 | [`docs/ai-context/tech-stack.md`](docs/ai-context/tech-stack.md)（不用動 agent 檔） |
| 開發規範 | [`docs/ai-context/conventions.md`](docs/ai-context/conventions.md) |
| 新增 / 修改 agent | 編輯 [`.claude/agents/xxx.md`](.claude/agents/)，在本文件的團隊清單加一行 |
| 新增業務術語 | 加到 [`docs/ai-context/glossary.md`](docs/ai-context/glossary.md) |
| 產品方向變動 | 改本文件的「專案總綱」區 |
| 三階定價規則 | **同時**改 [`CartPricingService.php`](backend/app/Services/CartPricingService.php) + [`pricing.ts`](frontend/src/lib/pricing.ts)；本文件的表格也要對齊 |

---

## ⚠️ 重要提醒給 Claude

作為在這個專案工作的 AI：

1. **永遠先讀這份 `CLAUDE.md`**（Claude Code 會自動給你）
2. **需要具體規範時讀 `docs/ai-context/` 對應檔案**
3. **扮演某個 agent 角色時，讀 `.claude/agents/` 裡的角色檔**
4. **當使用者的要求超出你能力或不在你的角色範圍**，明確說「這該由 XXX agent 處理」而不是硬答
5. **不確定時問使用者，不要自作主張**
6. **敏感操作（delete、drop、force-push、deploy 上線、Cloudflare purge everything）一定要使用者確認**
7. **修 bug 先寫 test**（尤其 Filament Resource / Widget — 曾因漏測試把 `/admin/visits` 500 上線過，教訓）

---

## 📝 最後

這個專案的 AI 團隊不是玩具，是**真正的生產力工具**。用對了能幫團隊省下大量時間、提升品質。用錯了等於多一個會 hallucinate 的同事。

請嚴肅對待，持續優化這份章程。

— CTO / CEO · FP 朵朵團隊
