<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regency extends Model
{
    protected $table     = 'regencies';
    protected $keyType   = 'string';
    public $incrementing = false;
    public $timestamps   = false;

    protected $fillable = ['id', 'province_id', 'name'];

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function profiles()
    {
        return $this->hasMany(Profile::class, 'regency_id');
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'region_regencies', 'regency_id', 'region_id');
    }
}
