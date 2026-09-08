<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crew extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'profile_id', 'nama', 'nama_panggilan', 'jabatan', 'jabatan_code_id',
        'email', 'jabatan_media', 'niam', 'no_wa', 'status', 'skill',
        'catatan', 'alamat_asal', 'prinsip_hidup', 'photo_url', 'cv_url',
        'xp_level', 'is_pic',
    ];

    protected $casts = ['skill' => 'array'];

    public function jabatanCode()
    {
        return $this->belongsTo(JabatanCode::class, 'jabatan_code_id');
    }

    /**
     * Invoice aktivasi kru, kalau ada. Kru yang masih di dalam kuota Golden 3
     * sengaja tidak punya baris ini karena tidak melewati alur pembayaran.
     */
    public function activationPayment()
    {
        return $this->hasOne(Payment::class, 'reference_id')
            ->where('payment_type', 'crew_activation')
            ->where('reference_type', 'crew');
    }

    public function profile()
    {
        return $this->belongsTo(PesantrenProfile::class, 'profile_id');
    }
}
