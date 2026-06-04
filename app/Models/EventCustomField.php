<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EventCustomField extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'label',
        'type',
        'options',
        'is_required',
        'order_num',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'order_num' => 'integer',
    ];
}
