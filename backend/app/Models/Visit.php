<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Human visit log — one row per page view.
 *
 * Paired with AiVisit (ai_visits_daily) which stores bots; this table
 * stores real humans. Intentional separation: AI rows are upsert-aggregated,
 * human rows are raw events for later analysis.
 */
class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id', 'session_id', 'ip', 'country', 'region',
        'user_agent', 'device_type', 'os', 'os_version', 'browser', 'browser_version',
        'referer_source', 'referer_url',
        'utm_source', 'utm_medium', 'utm_campaign',
        'landing_path', 'path',
        'customer_id', 'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
