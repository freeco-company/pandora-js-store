<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordNotification extends Model
{
    protected $fillable = [
        'channel', 'title', 'success', 'http_status', 'sent_at',
    ];

    protected $casts = [
        'success' => 'bool',
        'sent_at' => 'datetime',
    ];
}
