<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesantrenClaim extends Model
{
    protected $table = 'pesantren_claims';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'pesantren_directory_id', 'pesantren_name', 'jenis_pengajuan', 'status',
        'region_id', 'kecamatan', 'nama_pengelola', 'email_pengelola',
        'dokumen_bukti_url', 'mpj_id_number', 'notes', 'approved_by',
        'approved_at', 'regional_approved_at', 'is_claimed',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'user_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'pesantren_claim_id');
    }

    public function otpVerifications()
    {
        return $this->hasMany(OtpVerification::class, 'pesantren_claim_id');
    }
}
