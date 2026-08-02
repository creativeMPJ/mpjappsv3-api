<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSpeaker extends Model
{
    protected $table = 'event_speakers';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_id',
        'name',
        'title',
        'phone',
        'photo_url',
        'bio',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
