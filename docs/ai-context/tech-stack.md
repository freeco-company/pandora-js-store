# 團隊技術棧（預設）

本檔案由所有 agent 共同引用。當 agent 需要判斷技術時，先看這份檔案。

> 這份檔案描述的是「團隊預設」，不是「永遠都用這個」。專案實際狀況以 `package.json` / `composer.json` / 現況為準。

## 後端

- **語言**：PHP 8.2+
- **框架**：Laravel 10+
- **ORM**：Eloquent
- **認證**：依專案而定（先看 `composer.json`）
  - 有 `laravel/sanctum` → Sanctum
  - 有 `laravel/passport` → Passport
  - 都沒有且要新加 → 建議 Sanctum
- **Queue**：依專案
  - 有 `laravel/horizon` → Horizon + Redis
  - 沒有 → 預設 Redis driver
- **Debug**：依專案
  - 有 `laravel/telescope` → Telescope（local / staging only）

## 資料庫

- **主 DB**：MariaDB 10.6+
- **注意**：**MariaDB 不是 MySQL**，語法有差（尤其 JSON 函數、CTE、window function）
- **Cache / Queue**：Redis
- **Search**（如果有）：Meilisearch / Typesense / Algolia（依專案）

## 前端

- **框架**：React 或 Vue（看 `package.json`）
  - React 模式：Next.js 14+ 或 Vite + React 18+
  - Vue 模式：Vue 3 + Vite + Composition API
- **語言**：TypeScript（嚴格模式）
- **樣式**：Tailwind CSS
- **狀態**：
  - React → Zustand
  - Vue → Pinia
- **表單**：
  - React → react-hook-form + zod
  - Vue → VeeValidate + zod
- **資料拉取**：TanStack Query
- **Icon**：Lucide（兩邊通用）
- **Date**：dayjs 或 date-fns

## 部署

- **雲端**：AWS / GCP / Azure（看專案）
- **容器**：看專案（Dockerfile / docker-compose）
- **IaC**：看專案（Terraform / Pulumi / CloudFormation / Bicep）
- **CI**：GitHub Actions / GitLab CI / Cloud Build（看專案）

## 監控

- **APM**：Sentry（error tracking）+ 雲端原生工具（CloudWatch / Stackdriver / Azure Monitor）
- **Log**：集中式 log 系統（視部署環境）
- **Uptime**：UptimeRobot / Better Uptime

## 測試

- **後端**：Pest 或 PHPUnit（Laravel Feature Test 為主）
- **前端**：Vitest（unit）+ Playwright（e2e）
- **Static analysis**：PHPStan / Larastan（後端）、`tsc --noEmit`（前端）
- **Lint**：Laravel Pint（後端）、ESLint + Prettier（前端）

## 語言與區域

- **主要市場**：繁體中文（台灣）+ 英文
- **時區**：後端統一 UTC，前端依使用者偏好轉換
- **Currency**：依產品，多租戶要支援多幣別
