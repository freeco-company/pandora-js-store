# Franchise Webhook Playbook (母艦 → 朵朵)

> **Receiver side**: pandora-meal `POST /api/internal/franchisee/webhook` (PR #96, prod live)
> **Publisher side**: this repo (pandora.js-store)
> **Direction**: 母艦 (source of truth for who is 加盟夥伴) → 朵朵 (unlocks FP-gated content)

## What it does

當母艦端 `customers.is_franchisee` 翻牌：

```
admin toggle in Filament (or order auto-grant)
  ↓
Customer::update(['is_franchisee' => true])
  ↓
CustomerObserver::updated()  ← detects wasChanged('is_franchisee')
  ↓
FranchiseEventPublisher::dispatchActivated()
  ↓
Insert row into `franchise_outbox_events` (pending)
  ↓
SendFranchiseWebhookJob (immediate dispatch + sweeper fallback every 1 min)
  ↓
HMAC SHA256 sign + POST to 朵朵
  ↓
朵朵 verifies signature, dedupes by event_id, sets users.is_franchisee
  ↓
朵朵 unlocks fp_crown / fp_chef / fp_apron_premium / fp_recipe / FP food
```

## Deploy checklist

### 1. Generate a shared secret

```bash
openssl rand -hex 32
# → e.g. a3f1e8c9d2b4...   (use the same value on both sides)
```

### 2. Set on 朵朵 (pandora-meal) prod

```env
MOTHERSHIP_FRANCHISE_WEBHOOK_SECRET=<the_value_above>
```

Restart pandora-meal php-fpm.

### 3. Set on 母艦 (this repo) prod

```env
FRANCHISE_WEBHOOK_URL=https://meal-api.js-store.com.tw/api/internal/franchisee/webhook
MOTHERSHIP_FRANCHISE_WEBHOOK_SECRET=<the_same_value>
FRANCHISE_WEBHOOK_TIMEOUT=10
FRANCHISE_WEBHOOK_MAX_ATTEMPTS=5
```

Restart 母艦 php-fpm + queue worker.

### 4. Run migration

```bash
php artisan migrate
# Creates: franchise_outbox_events
# Adds: customers.is_franchisee, customers.franchisee_verified_at
```

### 5. Smoke test integration

- Open `/admin/customers/{id}/edit`
- Toggle **「已加盟」** ON, save
- Check 母艦 DB: `franchise_outbox_events` has new row, `dispatched_at` filled within 1 min
- Check 朵朵 DB: `franchisee_webhook_nonces` has matching `event_id`
- Check 朵朵 user record: `is_franchisee = 1`

## Operations

### Inspect pending / failed events

```sql
-- Pending (not yet sent)
SELECT id, event_id, event_type, target_email, attempts, last_status_code, last_error
FROM franchise_outbox_events
WHERE dispatched_at IS NULL
ORDER BY id DESC
LIMIT 50;

-- Dead-lettered (5 attempts, stopped retrying)
SELECT id, event_id, event_type, target_email, attempts, last_status_code, last_error, created_at
FROM franchise_outbox_events
WHERE dispatched_at IS NULL AND attempts >= 5
ORDER BY id DESC;
```

### Manually retry a stuck event

After fixing the upstream issue (e.g. corrected secret, restored 朵朵 endpoint):

```sql
UPDATE franchise_outbox_events
SET attempts = 0, next_retry_at = NULL, last_error = NULL
WHERE id = <THE_ID>;
```

The next minute's `franchise:dispatch-pending` schedule will pick it up.

### Force-replay all dead letters

```sql
UPDATE franchise_outbox_events
SET attempts = 0, next_retry_at = NULL
WHERE dispatched_at IS NULL AND attempts >= 5;
```

### Replay a single customer's status (no toggle needed)

If you need to re-send the current state without changing it:

```php
// php artisan tinker
$customer = \App\Models\Customer::find(123);
app(\App\Services\Franchise\FranchiseEventPublisher::class)
    ->dispatchActivated($customer, source: 'manual_replay');
```

## Rotating the shared secret

1. On 朵朵 (pandora-meal): add **second** secret env var (dual accept) and deploy
2. On 母艦: switch `MOTHERSHIP_FRANCHISE_WEBHOOK_SECRET` to new value
3. Wait until `franchise_outbox_events` has no pending older than 24 h
4. On 朵朵: remove the old secret, redeploy

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Events stuck pending, `last_error` says misconfigured | env not set / typo in URL | Set both env vars, restart php-fpm + queue |
| 朵朵 logs 401 invalid signature | Secrets don't match | Re-paste secret on both sides, check no trailing whitespace |
| 朵朵 logs 401 timestamp out of window | Clock skew | `timedatectl` on both servers; `>5 min` skew = NTP broken |
| Events sent but 朵朵 user not updated | 朵朵 user lookup miss (uuid + email both unmatched) | 朵朵 returns 200 but `unmatched=true`; user logs into 朵朵 first → JIT pull → next webhook will match |
| All events dead-letter | 朵朵 endpoint down | Check 朵朵 health, then `UPDATE … attempts=0` |

## TODO (not yet built)

- [ ] Filament Widget on `/admin` showing pending / dead-letter outbox count (red badge if >0)
- [ ] Discord alert when any event reaches dead letter
- [ ] Auto-trigger from order flow (NT$6,600+ first order → set `is_franchisee=true`); wired via `OrderConversionObserver` once business rule is finalised
- [ ] Backfill command to send `franchisee.activated` for existing `is_franchisee=true` customers (only needed if we manually flag people before deploying this code)

## File map

- Migration: `database/migrations/2026_05_01_120000_add_franchisee_to_customers_table.php`
- Migration: `database/migrations/2026_05_01_120100_create_franchise_outbox_events_table.php`
- Model: `app/Models/FranchiseOutboxEvent.php`
- Model (modified): `app/Models/Customer.php` — added `is_franchisee` / `franchisee_verified_at` to fillable + casts
- Service: `app/Services/Franchise/FranchiseEventPublisher.php`
- Job: `app/Jobs/SendFranchiseWebhookJob.php`
- Command: `app/Console/Commands/FranchiseDispatchPending.php`
- Schedule: `routes/console.php` (every minute, `franchise:dispatch-pending`)
- Observer (modified): `app/Observers/CustomerObserver.php` — fires on `wasChanged('is_franchisee')`
- Filament (modified): `app/Filament/Resources/CustomerResource.php` — toggle + table column + filter
- Filament (modified): `app/Filament/Resources/CustomerResource/Pages/EditCustomer.php` — auto-fill `franchisee_verified_at`
- Config: `config/services.php` — `franchise_webhook.*`
- Tests: `tests/Feature/FranchiseWebhookPublisherTest.php` (8 cases)
