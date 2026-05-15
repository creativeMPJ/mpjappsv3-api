<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubResource extends Model
{
    protected $table = 'hub_resources';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'category',
        'resource_type',
        'file_url',
        'external_url',
        'mime_type',
        'file_size',
        'visibility_scopes',
        'is_published',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'visibility_scopes' => 'array',
        'is_published' => 'boolean',
    ];
}
