# 團隊開發規範

所有 agent 都應遵守以下規範。遇到專案本身有覆寫的規範（例如 `.editorconfig`、`CONTRIBUTING.md`）以專案為準。

## Git Workflow

### 分支策略

- `main` / `master`：正式環境，受保護分支
- `develop`（可選）：開發整合分支
- `feature/[ticket-id]-[short-desc]`：功能分支
- `bugfix/[ticket-id]-[short-desc]`：修 bug
- `hotfix/[ticket-id]-[short-desc]`：緊急修正
- `chore/[short-desc]`：雜項（文件、重構）

範例：`feature/PROJ-123-invoice-bulk-export`

### Commit Message（Conventional Commits）

格式：`<type>(<scope>): <subject>`

常用 type：
- `feat`：新功能
- `fix`：修 bug
- `docs`：文件
- `style`：格式（不影響功能）
- `refactor`：重構（不加功能、不修 bug）
- `perf`：效能優化
- `test`：測試
- `chore`：雜項（build、CI、依賴）

範例：
```
feat(invoice): add bulk export API
fix(auth): correct Sanctum token expiration logic
refactor(billing): extract ChargeService from controller
docs(api): update OpenAPI spec for v2 endpoints
```

### Pull Request

**PR 標題**：`[TICKET-ID] 簡短描述`
範例：`[PROJ-123] 發票批次匯出功能`

**PR 描述範本**：
```markdown
## 關聯
- Ticket: [PROJ-123](link)
- PRD: [link]

## 變更內容
- 新增 `/api/invoices/export` endpoint
- 新增 `InvoiceExportService`
- 加 feature test

## 測試方式
1. 登入
2. 進入發票列表
3. 選多張後點匯出
4. 檢查下載的 CSV

## 截圖 / 錄影
（如果有 UI 改動）

## Checklist
- [ ] 有測試
- [ ] 自測過
- [ ] staging 跑過
- [ ] 文件更新
- [ ] 不會 break 既有功能
```

## Code Style

### PHP / Laravel

- **PSR-12** 為基礎
- 用 **Laravel Pint** 自動格式化
- 類別命名：`PascalCase`
- 方法、變數：`camelCase`
- 常數：`UPPER_SNAKE_CASE`
- 檔案一律 `namespace` 對應資料夾結構

### TypeScript / React / Vue

- **ESLint + Prettier** 強制執行
- 元件檔：`PascalCase.tsx` / `PascalCase.vue`
- hook / composable：`useXxx`
- 型別 / interface：`PascalCase`
- 變數 / 函數：`camelCase`
- 常數：`UPPER_SNAKE_CASE`

## 目錄結構

### Laravel 後端建議

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/             # API Controllers 獨立
│   ├── Middleware/
│   ├── Requests/            # Form Request
│   └── Resources/           # API Resource
├── Services/                # Business logic
├── Repositories/            # 複雜 query（可選）
├── Models/
├── Policies/
├── Jobs/
├── Events/
├── Listeners/
├── Exceptions/
└── Actions/                 # Single-action classes（可選）
```

### 前端（React 為例）

```
src/
├── pages/                   # Next.js App Router 或 Routes
├── components/
│   ├── shared/              # 跨頁共用
│   └── [feature]/           # 功能專屬
├── hooks/
├── lib/
│   ├── api/                 # API client + endpoint 封裝
│   └── utils/
├── schemas/                 # zod schemas
├── types/
└── stores/                  # Zustand
```

### 前端（Vue）

```
src/
├── pages/                   # Vue Router
├── components/
│   ├── shared/
│   └── [feature]/
├── composables/
├── lib/
│   ├── api/
│   └── utils/
├── schemas/
├── types/
└── stores/                  # Pinia
```

## 檔名與命名

- **元件檔**：`InvoiceList.tsx` / `InvoiceList.vue`
- **hook**：`useInvoices.ts`
- **Service**：`InvoiceExportService.php`
- **Test**：`InvoiceExportTest.php` / `InvoiceList.test.tsx`
- **Migration**：Laravel 自動命名，`2026_04_21_120000_create_invoices_table.php`

## 測試規範

- **後端**：新 feature 至少一個 Feature Test（happy path + error path + permission test）
- **前端**：複雜元件寫 unit test；關鍵 user flow 寫 e2e
- **覆蓋率**：不硬性要求 %，但權限 / 金流 / 資料邊界必須有測試
- **測試命名**：描述行為，不是方法名
  - ✅ `test('user cannot view invoices from other organization')`
  - ❌ `test('getInvoiceMethod')`

## 文件規範

- **API doc**：每個 endpoint 至少在 OpenAPI / Scribe 裡有 description + example
- **Code comment**：寫「為什麼」不寫「做什麼」
- **TODO / FIXME**：一定要有 ticket 編號
  - ✅ `// TODO(PROJ-456): refactor when caching layer ready`
  - ❌ `// TODO: fix later`

## 環境變數

- `.env` 不進 git
- `.env.example` 要更新（有新變數時）
- Secret（API key、密碼、token）一律走雲端 secret manager
- Local 開發可以放 `.env`，production 從 secret manager 注入

## Review 標準

- 每個 PR 至少 1 個 reviewer
- 權限 / 資安相關 PR 必須 security-engineer 再看一次
- API 契約改動必須 api-tester 跑一輪
- 上線前走 reality-checker 關卡
