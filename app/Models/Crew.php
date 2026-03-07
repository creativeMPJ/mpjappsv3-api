<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crew extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'profile_id', 'nama', 'jabatan', 'jabatan_code_id',
        'niam', 'skill', 'xp_level',
    ];

    protected $casts = ['skill' => 'array'];

    public function jabatanCode()
    {
        return $this->belongsTo(JabatanCode::class, 'jabatan_code_id');
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}
