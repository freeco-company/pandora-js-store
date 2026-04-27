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
        'google_id', 'line_id', 'membership_level',
        'address_city', 'address_district', 'address_detail', 'address_zip',
        'wp_user_id',
        'streak_days', 'last_active_date', 'current_outfit', 'current_backdrop',
        'last_serendipity_at', 'activation_progress', 'total_spent', 'total_orders',
        'referral_code', 'referred_by_customer_id', 'referral_reward_granted',
    ];

    /** Auto-generate a unique referral_code on first save if blank. */
    protected static function booted(): void
    {
        static::creating(function (Customer $c) {
            if (! $c->referral_code) {
                $c->referral_code = static::generateReferralCode();
            }
        });
    }

    public static function generateReferralCode(): string
    {
        do {
            // 8-char upper-alnum, avoiding confusing 0/O/1/I
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 8; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        } while (static::where('referral_code', $code)->exists());
        return $code;
    }

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

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function identities()
    {
        return $this->hasMany(CustomerIdentity::class);
    }

    /**
     * 由 identity 反查 customer。OAuth callback / dedupe 用。
     * 回傳 null 表示沒人擁有這條 identity。
     */
    public static function findByIdentity(string $type, ?string $value): ?Customer
    {
        if (!$value) return null;
        $row = CustomerIdentity::where('type', $type)
            ->where('value', $value)
            ->first();
        return $row ? static::find($row->customer_id) : null;
    }
}
