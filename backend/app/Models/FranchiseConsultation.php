<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ?string $pandora_user_uuid
 * @property string $name
 * @property string $phone
 * @property ?string $email
 * @property ?string $source
 * @property ?string $note
 * @property string $status
 * @property ?string $admin_note
 * @property ?Carbon $contacted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FranchiseConsultation extends Model
{
    protected $fillable = [
        'pandora_user_uuid', 'name', 'phone', 'email',
        'source', 'note',
        'status', 'admin_note', 'contacted_at',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
    ];
}
