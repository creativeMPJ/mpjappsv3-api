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
}
