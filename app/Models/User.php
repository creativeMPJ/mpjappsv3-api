<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'email', 'password_hash', 'reff_type', 'reff_id'];
    protected $hidden = ['password_hash'];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'role'  => $this->activeRole()?->nama ?? 'Pengguna Pesantren',
        ];
    }

    public function activeRole(): ?Role
    {
        return $this->userRoles()
            ->with('roleDetail')
            ->orderBy('created_at', 'desc')
            ->first()?->roleDetail;
    }

    public function profile()
    {
        return $this->hasOne(PesantrenProfile::class, 'user_id');
    }

    public function userRoles()
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }
}
