<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'role', 'status_account', 'status_payment', 'profile_level',
        'nama_pesantren', 'nama_pengasuh', 'nama_media', 'alamat_singkat',
        'no_wa_pendaftar', 'nip', 'city_id', 'region_id', 'logo_url',
        'foto_pengasuh_url', 'sk_pesantren_url', 'latitude', 'longitude',
        'jumlah_santri', 'tipe_pesantren', 'program_unggulan', 'sejarah',
        'visi_misi', 'social_links', 'is_alumni', 'niam', 'alamat_lengkap',
        'kecamatan', 'desa', 'kode_pos', 'maps_link', 'ketua_media',
        'tahun_berdiri', 'jumlah_kru', 'logo_media_path', 'foto_gedung_path',
        'website', 'instagram', 'facebook', 'youtube', 'tiktok', 'jenjang_pendidikan',
    ];

    protected $casts = [
        'program_unggulan'  => 'array',
        'social_links'      => 'array',
        'jenjang_pendidikan'=> 'array',
        'is_alumni'         => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function crews()
    {
        return $this->hasMany(Crew::class, 'profile_id');
    }

    public function claims()
    {
        return $this->hasMany(PesantrenClaim::class, 'user_id');
    }
}
