<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EventFinanceTransaction extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'event_id',
        'type',
        'source',
        'title',
        'description',
        'amount',
        'status',
        'transaction_date',
        'participant_id',
        'payment_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'transaction_date' => 'datetime',
    ];
}
