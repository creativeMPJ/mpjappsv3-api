<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'guest_id',
        'registration_type',
        'registration_path',
        'ticket_code',
        'qr_token',
        'ticket_status',
        'payment_status',
        'attendance_status',
        'price_amount',
        'unique_amount',
        'payment_id',
        'payment_proof_path',
        'attended_at',
        'participant_email',
        'niam',
        'notes',
        // Data peserta Kemah Film
        'nama',
        'no_whatsapp',
        'participant_name',
        'participant_phone',
        'asal_pesantren',
        'alamat_pesantren',
        'pesantren_profile_id',
        'kemampuan_bidang',
        'tingkat_kemampuan',
        'pengalaman',
        'link_karya',
        'surat_delegasi_path',
        'bukti_pembayaran_path',
        'status',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
        'price_amount' => 'integer',
        'unique_amount' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->id ??= (string) Str::uuid());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class, 'crew_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(EventGuest::class, 'guest_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->participant_name
            ?: $this->nama
            ?: $this->crew?->nama
            ?: $this->guest?->full_name
            ?: '-';
    }
}
