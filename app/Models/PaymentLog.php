<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PaymentLog extends Model
{
    protected $table = 'payment_logs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'payment_id',
        'actor_user_id',
        'action',
        'from_status',
        'to_status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
