<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_id  UUID v7 — 也作為朵朵端 nonce
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property ?int $customer_id
 * @property ?string $target_uuid
 * @property ?string $target_email
 * @property \Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $dispatched_at
 * @property int $attempts
 * @property ?\Illuminate\Support\Carbon $next_retry_at
 * @property ?string $last_status_code
 * @property ?string $last_error
 */
class FranchiseOutboxEvent extends Model
{
    protected $table = 'franchise_outbox_events';

    public $timestamps = false;  // 只有 created_at；dispatched_at 由 worker 設

    protected $fillable = [
        'event_id',
        'event_type',
        'payload',
        'customer_id',
        'target_uuid',
        'target_email',
        'dispatched_at',
        'attempts',
        'next_retry_at',
        'last_status_code',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public const EVENT_ACTIVATED = 'franchisee.activated';
    public const EVENT_DEACTIVATED = 'franchisee.deactivated';

    /** 5 次 attempts 後留在 table，等人工 SQL 重置 dispatched_at / attempts */
    public const MAX_ATTEMPTS = 5;
}
