<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingPackage extends Model
{
    protected $table = 'pricing_packages';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'category', 'harga_paket', 'harga_diskon', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
