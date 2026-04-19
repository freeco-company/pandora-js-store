<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMetric extends Model
{
    protected $table = 'seo_metrics_weekly';
    public $timestamps = false;

    protected $fillable = [
        'measured_on', 'url', 'label', 'strategy',
        'perf_score', 'lcp_ms', 'cls_x1000', 'tbt_ms', 'inp_ms',
        'opportunities', 'created_at',
    ];

    protected $casts = [
        'measured_on'   => 'date',
        'opportunities' => 'array',
        'created_at'    => 'datetime',
    ];
}
