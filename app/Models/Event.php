<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'description', 'date', 'location', 'status', 'member_price', 'public_price', 'certificate_enabled'];
    protected $casts = ['date' => 'datetime'];
}
