<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MascotOutfit extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_id', 'code', 'unlocked_at'];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
