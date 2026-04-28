<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_type
 * @property ?int $customer_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $retry_count
 * @property ?\Illuminate\Support\Carbon $next_retry_at
 * @property ?\Illuminate\Support\Carbon $sent_at
 * @property ?string $last_error
 */
class OutboxIdentityEvent extends Model
{
    protected $table = 'outbox_identity_events';

    protected $fillable = [
        'event_type',
        'customer_id',
        'payload',
        'status',
        'retry_count',
        'next_retry_at',
        'sent_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DEAD_LETTER = 'dead_letter';

    public const MAX_RETRIES = 5;
}
