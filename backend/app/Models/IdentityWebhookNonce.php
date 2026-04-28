<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 收到的 platform webhook event_id，UNIQUE 用來防 replay。
 *
 * @property int $id
 * @property string $event_id
 * @property Carbon $received_at
 */
class IdentityWebhookNonce extends Model
{
    protected $table = 'identity_webhook_nonces';

    public $timestamps = false;

    protected $fillable = ['event_id', 'received_at'];

    protected $casts = [
        'received_at' => 'datetime',
    ];
}
