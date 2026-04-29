<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Marketing short link (/p/{code}) that 302s to a long URL with utm_* params.
 * Created from /admin so social posts can carry a clean, branded URL while
 * still feeding orders.utm_* attribution at checkout.
 */
class ShortLink extends Model
{
    protected $fillable = [
        'code', 'target_url', 'label', 'bundle_id', 'campaign',
        'click_count', 'created_by', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'click_count' => 'integer',
    ];

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate an unused 6-char base36 code. Retries on collision; after a
     * handful of misses bumps to 8 chars (table size would have to be huge
     * for 6 to actually exhaust).
     */
    public static function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $len = $attempt < 5 ? 6 : 8;
            $code = strtolower(Str::random($len));
            // Str::random can include uppercase + symbols-free alphanumerics;
            // strtolower keeps URLs case-insensitive friendly.
            $code = preg_replace('/[^a-z0-9]/', '', $code);
            if (strlen($code) < $len) continue;
            if (! self::where('code', $code)->exists()) {
                return $code;
            }
        }
        // Astronomically unlikely fallback.
        return strtolower(Str::random(12));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function fullUrl(): string
    {
        // Short links live on the customer-facing domain (where /p/ resolves
        // via Laravel routes, fronted by nginx), not on /admin's host.
        $base = rtrim(config('services.frontend.url') ?: config('app.url'), '/');
        return $base . '/p/' . $this->code;
    }
}
