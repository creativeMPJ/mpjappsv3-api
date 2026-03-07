<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'pesantren_claim_id', 'pricing_package_id',
        'base_amount', 'unique_code', 'total_amount', 'status',
        'proof_file_url', 'rejection_reason', 'verified_by', 'verified_at',
    ];

    public function claim()
    {
        return $this->belongsTo(PesantrenClaim::class, 'pesantren_claim_id');
    }

    public function user()
    {
        return $this->belongsTo(Profile::class, 'user_id');
    }

    public function pricingPackage()
    {
        return $this->belongsTo(PricingPackage::class, 'pricing_package_id');
    }
}
