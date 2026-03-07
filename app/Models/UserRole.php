<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';
    protected $table     = 'user_roles';

    protected $fillable = ['id', 'user_id', 'role'];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
}
