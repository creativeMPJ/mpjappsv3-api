<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAttendanceLog extends Model
{
    protected $fillable = [
        'event_id',
        'participant_id',
        'qr_token',
        'scanned_by_name',
        'scanner_device',
        'scanned_at',
        'success',
        'failure_reason',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'success' => 'boolean',
    ];
}
