<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'metable_type', 'metable_id',
        'title', 'description', 'focus_keyword',
        'og_image', 'canonical_url', 'robots', 'schema_json',
    ];

    protected $casts = [
        'schema_json' => 'array',
    ];

    public function metable()
    {
        return $this->morphTo();
    }
}
