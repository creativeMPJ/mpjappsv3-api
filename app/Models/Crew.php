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

    public function profile()
    {
        return $this->belongsTo(PesantrenProfile::class, 'profile_id');
    }
}
