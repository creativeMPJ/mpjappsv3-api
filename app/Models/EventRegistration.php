<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $table = 'event_registrations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_id',
        'user_id',
        'profile_id',
        'crew_id',
        'registration_type',
        'ticket_code',
        'ticket_status',
        'price_amount',
        'payment_id',
        'participant_name',
        'participant_phone',
        'participant_email',
        'niam',
        'notes',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function profile()
    {
        return $this->belongsTo(PesantrenProfile::class, 'profile_id');
    }

    public function crew()
    {
        return $this->belongsTo(Crew::class, 'crew_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
