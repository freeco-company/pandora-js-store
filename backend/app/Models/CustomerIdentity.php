<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 一條 customer_identities row 代表「customer X 擁有 type=Y / value=Z 的身分」。
 * (type, value) 全表唯一，所以可由 (type, value) 反查唯一 customer。
 */
class CustomerIdentity extends Model
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_GOOGLE = 'google_id';
    public const TYPE_LINE = 'line_id';

    protected $fillable = [
        'customer_id', 'type', 'value', 'verified_at', 'is_primary',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
