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
        // Memanggil builder relasi ($this->userRoles()->...) SELALU mengirim query
        // baru dan mengabaikan hasil eager load, sehingga pemanggil yang sudah
        // melakukan with(['user.userRoles.roleDetail']) tetap memicu satu query per
        // baris. Kalau relasinya sudah dimuat, urutkan saja di memori.
        if ($this->relationLoaded('userRoles')) {
            // sortByDesc memakai uasort yang stabil di PHP 8, jadi semantiknya sama
            // dengan orderBy('created_at', 'desc'): baris terbaru menang, dan saat
            // created_at seri urutan asli dari database yang dipakai.
            return $this->userRoles
                ->sortByDesc('created_at')
                ->first()?->roleDetail;
        }

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
