<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventCheckin extends Model
{
    protected $table = 'event_checkins';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_registration_id',
        'checked_in_by',
        'checked_in_at',
    ];

    const CREATED_AT = 'checked_in_at';
    const UPDATED_AT = null;

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];
}
