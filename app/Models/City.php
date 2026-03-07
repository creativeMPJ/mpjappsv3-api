<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'name', 'region_id'];

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }
}
