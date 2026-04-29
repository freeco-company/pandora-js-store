<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'pandora_user_uuid',
        'name', 'email', 'phone', 'password', 'is_vip',
        'google_id', 'line_id', 'membership_level',
        'address_city', 'address_district', 'address_detail', 'address_zip',
        'wp_user_id',
        'streak_days', 'last_active_date', 'current_outfit', 'current_backdrop',
        'last_serendipity_at', 'activation_progress', 'total_spent', 'total_orders',
        'referral_code', 'referred_by_customer_id', 'referral_reward_granted',
        // ADR-009 Phase B inbound — group gamification mirror (driven by py-service webhook)
        'group_level', 'group_level_xp', 'group_level_name_zh', 'group_level_name_en',
        'outfits_owned', 'group_level_updated_at',
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
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
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
        'outfits_owned' => 'array',
        'group_level' => 'integer',
        'group_level_xp' => 'integer',
        'group_level_updated_at' => 'datetime',
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
     * 由 identity 反查 customer。OAuth callback / dedupe / 結帳合併用。
     *
     * 兩階段查詢：
     *   1. 先查 customer_identities（支援多 identity，例如客人換 email 後舊 email 還能找回原帳號）
     *   2. 找不到時 fallback 查 customers 表對應欄位（過渡期相容：identities backfill 還沒
     *      跑完、Observer 上線前已存在的 customer 用得到）
     *
     * 過渡期結束後可以移除 fallback，但留著的成本極小、避免 regression 風險高。
     *
     * 回傳 null 表示沒人擁有這條 identity。
     */
    public static function findByIdentity(string $type, ?string $value): ?Customer
    {
        if (! $value) {
            return null;
        }

        $row = CustomerIdentity::where('type', $type)
            ->where('value', $value)
            ->first();
        if ($row) {
            return static::find($row->customer_id);
        }

        // Fallback：identities 表沒這條 → 查 customers 表的同名欄位
        if (in_array($type, ['email', 'phone', 'google_id', 'line_id'], true)) {
            return static::where($type, $value)->first();
        }

        return null;
    }
}
