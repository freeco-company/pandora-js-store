# 團隊術語表（Glossary）

統一團隊和 agent 使用的詞彙，避免同一件事有多種說法。

> 這是範本，請根據你的產品實際狀況編輯。打 `[待補]` 的項目是留給你填的。

## 業務術語

### 使用者角色
- **Organization / Workspace / Tenant**：租戶（B2B 客戶的公司單位）
- **Admin**：組織內的管理員（能邀請人、改設定、看帳單）
- **Member / User**：組織內的一般成員
- **Owner**：組織的擁有者（通常創建者，有最高權限）
- **End User**：B2C 使用者（個人消費者）

> 選一組用法就好，不要混用「租戶」、「組織」、「團隊」。建議統一用 **Organization**（簡寫 `org`）。

### 訂閱與計費
- **Plan**：訂閱方案（Free / Pro / Business / Enterprise）
- **Subscription**：客戶的訂閱紀錄
- **MRR**：Monthly Recurring Revenue（月經常性收入）
- **ARR**：Annual Recurring Revenue（年經常性收入）
- **Churn**：流失（取消訂閱）
- **Upgrade / Downgrade**：升級 / 降級方案
- **Seat**：座位數（按人數計價時）

### 產品核心概念
- [待補] 你們產品的主要 entity 名稱
  - 範例：「發票（Invoice）」、「專案（Project）」、「訂單（Order）」

## 技術術語

### 系統
- **Monolith**：單體架構（我們目前架構）
- **Modular Monolith**：模組化單體（架構演進方向）
- **API**：RESTful JSON API（除非特別說明）

### 資料庫
- **Migration**：DB schema 變更
- **Seeder**：測試 / 初始資料
- **Factory**：測試資料產生器（Laravel）
- **Soft delete**：軟刪除（`deleted_at` 標記而非真刪）

### 認證授權
- **AuthN**：認證（你是誰）
- **AuthZ**：授權（你能做什麼）
- **Policy**：授權規則（Laravel Policy）
- **Token**：API 存取令牌（Sanctum / Passport）
- **Session**：Web 端登入狀態

### 部署
- **Environment**：環境（local / staging / production）
- **Rollback**：回滾
- **Feature flag**：功能開關（漸進釋出）
- **Canary**：金絲雀部署（給一小部分使用者）
- **Blue-green**：藍綠部署

## 縮寫速查

| 縮寫 | 全稱 | 意義 |
|---|---|---|
| PRD | Product Requirements Document | 產品需求文件 |
| ADR | Architecture Decision Record | 架構決策紀錄 |
| DDD | Domain-Driven Design | 領域驅動設計 |
| CRUD | Create Read Update Delete | 增刪改查 |
| DTO | Data Transfer Object | 資料傳輸物件 |
| SLO | Service Level Objective | 服務等級目標 |
| SLA | Service Level Agreement | 服務等級協議 |
| MTTR | Mean Time To Recovery | 平均修復時間 |
| RPO | Recovery Point Objective | 資料遺失可容忍程度 |
| RTO | Recovery Time Objective | 服務恢復時限 |
| CWV | Core Web Vitals | 網站核心指標 |
| CSR / SSR | Client / Server Side Rendering | 客戶端 / 伺服器渲染 |
| SPA | Single Page Application | 單頁應用 |
| PWA | Progressive Web App | 漸進式網頁應用 |
| CSP | Content Security Policy | 內容安全政策 |
| CORS | Cross-Origin Resource Sharing | 跨來源資源共享 |
| CSRF | Cross-Site Request Forgery | 跨站請求偽造 |
| XSS | Cross-Site Scripting | 跨站指令碼 |
| SQLi | SQL Injection | SQL 注入 |
| IDOR | Insecure Direct Object Reference | 不安全的直接物件參照 |
| KPI | Key Performance Indicator | 關鍵績效指標 |
| NPS | Net Promoter Score | 淨推薦值 |
| CSAT | Customer Satisfaction Score | 客戶滿意度 |
| CAC | Customer Acquisition Cost | 取得成本 |
| LTV | Lifetime Value | 終身價值 |
| CRO | Conversion Rate Optimization | 轉換率優化 |
| SEO | Search Engine Optimization | 搜尋引擎優化 |
| PLG | Product-Led Growth | 產品導向成長 |
| SLG | Sales-Led Growth | 銷售導向成長 |
| TAM | Total Addressable Market | 潛在市場總量 |

## 命名慣例

### 不要混用的詞
- 選 **Organization** 不要偶爾寫「Workspace」、「Team」、「Company」
- 選 **Member** 不要偶爾寫「User」、「Colleague」
- 選 **Invoice** 不要偶爾寫「Bill」、「Receipt」

### 英文 / 中文
- 技術文件、code、commit 用英文
- PRD、客戶面對的文案用繁體中文
- 混合時中文為主，技術名詞保留英文（像這份文件本身）

## 擴充
專案演進會有新術語，請持續更新這份檔案。**術語有分歧時，在這裡記錄一次，以此為準**。
