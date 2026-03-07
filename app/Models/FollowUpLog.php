<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUpLog extends Model
{
    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';
    protected $table     = 'follow_up_logs';

    protected $fillable = ['id', 'admin_id', 'claim_id', 'region_id', 'action_type'];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
}
