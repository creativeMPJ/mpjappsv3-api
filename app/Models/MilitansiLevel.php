<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MilitansiLevel extends Model
{
    protected $table = 'militansi_levels';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'min_xp',
        'color',
        'sort_order',
    ];
}
