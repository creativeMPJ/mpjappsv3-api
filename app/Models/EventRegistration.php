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
        'registration_type',
        'ticket_status',
        // Data peserta Kemah Film
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
}
