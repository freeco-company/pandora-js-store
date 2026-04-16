<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = ['customer_id', 'code', 'awarded_at'];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
