<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionalReport extends Model
{
    protected $table = 'regional_reports';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'region_id',
        'title',
        'description',
        'report_date',
        'file_url',
        'status',
        'created_by',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];
}
