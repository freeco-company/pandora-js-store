# API 回傳格式規範

所有 Laravel API endpoint 必須遵守以下格式。前端根據這份規範處理回傳。

## 成功回傳

### 單一資源

```json
{
  "data": {
    "id": "01HXXX...",
    "name": "Acme Corp",
    "created_at": "2026-04-21T10:00:00Z"
  }
}
```

### 列表（含分頁）

```json
{
  "data": [
    { "id": "...", "name": "..." },
    { "id": "...", "name": "..." }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 87,
    "last_page": 5
  },
  "links": {
    "first": "https://api.example.com/invoices?page=1",
    "last": "https://api.example.com/invoices?page=5",
    "prev": null,
    "next": "https://api.example.com/invoices?page=2"
  }
}
```

### 列表（cursor pagination，推薦用於大量資料）

```json
{
  "data": [...],
  "meta": {
    "per_page": 20,
    "next_cursor": "eyJpZCI6MTIzfQ",
    "prev_cursor": null
  }
}
```

### 無回傳內容（刪除、異動成功）

回傳 `204 No Content`，body 為空。

## 錯誤回傳

### 驗證錯誤（422）

Laravel 預設格式，**不要改**：

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "amount": ["The amount must be at least 1."]
  }
}
```

前端用 `errors.{field}[0]` 對應到表單欄位顯示錯誤。

### 業務錯誤（400 / 409）

```json
{
  "message": "Cannot delete invoice that has been paid",
  "errors": {
    "reason": ["invoice_already_paid"]
  }
}
```

- `message`：人話，可以直接顯示給使用者
- `errors.reason[0]`：機器可讀的 error code，前端用來做特殊處理

### 未認證（401）

```json
{
  "message": "Unauthenticated."
}
```

前端收到 401 → 清 token → 導向登入頁。

### 無權限（403）

```json
{
  "message": "This action is unauthorized."
}
```

### 找不到（404）

```json
{
  "message": "Resource not found."
}
```

**注意**：多租戶場景下，「別家租戶的資源」應該回 404（不是 403），避免洩漏資源存在。

### 速率限制（429）

```json
{
  "message": "Too Many Attempts."
}
```

Laravel 會自動加 `Retry-After` header。

### 伺服器錯誤（500）

Production 環境只回泛用訊息，**不要**洩漏 stack trace：

```json
{
  "message": "Server error. Please try again later."
}
```

錯誤會被 Sentry 等 APM 記錄，前端可以顯示「請聯絡客服」。

## HTTP 狀態碼使用原則

| 狀態碼 | 意義 | 使用情境 |
|---|---|---|
| 200 | OK | 成功 + 有回傳 |
| 201 | Created | POST 新建成功 |
| 204 | No Content | DELETE 成功、或 PUT/PATCH 成功但不需回資料 |
| 400 | Bad Request | 請求格式錯（JSON 壞了） |
| 401 | Unauthorized | 沒登入 / token 無效 |
| 403 | Forbidden | 已登入但沒權限 |
| 404 | Not Found | 資源不存在（或無權看） |
| 409 | Conflict | 衝突（例如重複 email） |
| 422 | Unprocessable Entity | 驗證失敗（Laravel 標準） |
| 429 | Too Many Requests | Rate limit |
| 500 | Internal Server Error | 伺服器錯（吞掉細節） |
| 503 | Service Unavailable | 維護中、外部 service 掛了 |

## Laravel 實作建議

### Controller 薄，回傳用 Resource

```php
// Controller
public function show(Invoice $invoice)
{
    Gate::authorize('view', $invoice);
    return new InvoiceResource($invoice);
}

// Resource
class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'amount' => $this->amount,
            'status' => $this->status,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

### 業務錯誤用自訂 Exception

```php
// app/Exceptions/BusinessException.php
class BusinessException extends Exception
{
    public function __construct(
        public string $reason,
        string $message = '',
        public int $statusCode = 400,
    ) {
        parent::__construct($message);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->message,
            'errors' => ['reason' => [$this->reason]],
        ], $this->statusCode);
    }
}

// 使用
throw new BusinessException(
    reason: 'invoice_already_paid',
    message: '已付款發票無法刪除',
    statusCode: 409,
);
```

### 分頁用 Laravel 原生

```php
// page-based
return InvoiceResource::collection(
    Invoice::where(...)->paginate(20)
);

// cursor-based（大量資料建議）
return InvoiceResource::collection(
    Invoice::where(...)->cursorPaginate(20)
);
```

## 時間格式

所有 timestamp 一律用 **ISO 8601 UTC**：

```
2026-04-21T10:00:00Z
```

或含毫秒：

```
2026-04-21T10:00:00.000Z
```

前端拿到後用 dayjs / date-fns 轉使用者時區。

## 金額格式

金額一律用**整數（最小單位）**傳遞，避免浮點數誤差：

- 新台幣：`1500`（= 1500 元，最小單位）
- 美金：`1500`（= $15.00，最小單位 cent）

`currency` 欄位必傳：

```json
{
  "amount": 1500,
  "currency": "TWD"
}
```

前端負責顯示格式化（`NT$1,500`）。

## ID 格式

- 內部 ID 推薦用 **ULID** 或 **UUID v7**（有時序性、URL safe）
- 不要用自增 int（會洩漏業務量）
- Laravel 10+ 原生支援 ULID / UUID primary key

## API 版本化

URL 前綴：`/api/v1/`, `/api/v2/`

破壞性改動 → 新版本，舊版至少維持 6 個月。
