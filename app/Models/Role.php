<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'nama', 'is_super_admin', 'akses'];

    protected $casts = [
        'akses'          => 'array',
        'is_super_admin' => 'boolean',
    ];

    private static array $enumMap = [
        'admin_pusat'    => 'Admin Pusat',
        'admin_regional' => 'Admin Wilayah',
        'admin_finance'  => 'Admin Keuangan',
        'coordinator'    => 'Koordinator',
        'user'           => 'Pengguna Pesantren',
        'crew'           => 'Kru Pesantren',
    ];

    public static function findByEnum(string $enum): ?self
    {
        $nama = self::$enumMap[$enum] ?? null;
        return $nama ? static::where('nama', $nama)->first() : null;
    }
}
