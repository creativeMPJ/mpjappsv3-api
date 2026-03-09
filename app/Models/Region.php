<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'name', 'code'];

    public function regencies()
    {
        return $this->belongsToMany(Regency::class, 'region_regencies', 'region_id', 'regency_id');
    }

    public function profiles()
    {
        return $this->hasMany(Profile::class, 'region_id');
    }
}
