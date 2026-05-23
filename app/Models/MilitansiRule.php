<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MilitansiRule extends Model
{
    protected $table = 'militansi_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'action_key',
        'label',
        'xp_value',
        'limit_type',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
