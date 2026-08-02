<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'actor_user_id', 'actor_role', 'action', 'target_type',
        'target_id', 'target_name', 'details', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
