<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'email', 'password_hash'];
    protected $hidden = ['password_hash'];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'role'  => $this->profile?->role ?? 'user',
        ];
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'id');
    }
}
