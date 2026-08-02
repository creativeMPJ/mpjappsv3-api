<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'title',
        'category',
        'event_type',
        'poster_path',
        'description',
        'date',
        'start_date',
        'registration_deadline',
        'location',
        'location_name',
        'location_gmaps',
        'status',
        'status_pendaftaran',
        'is_open_for_public',
        'is_paid',
        'member_price',
        'public_price',
        'price_niam',
        'price_public',
        'max_participants',
        'current_participants',
        'payment_method',
        'gateway_provider',
        'gateway_config',
        'bank_account_id',
        'speaker_id',
        'gdrive_lpj',
        'created_by',
        'certificate_enabled',
    ];

    protected $casts = [
        'date' => 'datetime',
        'start_date' => 'datetime',
        'registration_deadline' => 'datetime',
        'is_open_for_public' => 'boolean',
        'is_paid' => 'boolean',
        'gateway_config' => 'array',
        'member_price' => 'integer',
        'public_price' => 'integer',
        'price_niam' => 'integer',
        'price_public' => 'integer',
        'max_participants' => 'integer',
        'current_participants' => 'integer',
        'certificate_enabled' => 'boolean',
    ];

    public function customFields()
    {
        return $this->hasMany(EventCustomField::class, 'event_id')->orderBy('order_num');
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }

    public function speaker()
    {
        return $this->belongsTo(Speaker::class, 'speaker_id');
    }

    public function speakers()
    {
        return $this->hasMany(EventSpeaker::class, 'event_id');
    }
}
