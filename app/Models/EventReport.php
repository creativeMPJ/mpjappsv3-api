<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventReport extends Model
{
    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';
    protected $table     = 'event_reports';

    const CREATED_AT = 'submitted_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id', 'event_id', 'region_id', 'participation_count', 'notes', 'photo_url',
    ];
}
