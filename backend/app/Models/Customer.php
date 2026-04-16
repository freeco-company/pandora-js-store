<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'is_vip',
        'google_id', 'membership_level',
        'address_city', 'address_district', 'address_detail', 'address_zip',
        'wp_user_id',
        'streak_days', 'last_active_date', 'current_outfit', 'current_backdrop',
        'last_serendipity_at', 'activation_progress', 'total_spent', 'total_orders',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_vip' => 'boolean',
        'password' => 'hashed',
        'last_active_date' => 'date',
        'last_serendipity_at' => 'datetime',
        'activation_progress' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function achievements()
    {
        return $this->hasMany(Achievement::class);
    }

    public function outfits()
    {
        return $this->hasMany(MascotOutfit::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }
}
