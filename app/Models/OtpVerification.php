<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $table = 'otp_verifications';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'user_phone', 'otp_code', 'pesantren_claim_id',
        'is_verified', 'attempts', 'expires_at', 'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];
}
