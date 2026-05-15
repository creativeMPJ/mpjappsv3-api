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
        'payment_type', 'reference_type', 'reference_id', 'invoice_number',
        'transaction_reference', 'proof_file_url', 'rejection_reason',
        'verified_by', 'verified_at', 'submitted_at', 'expired_at',
        'cancelled_at', 'rejected_at', 'created_by', 'rejected_by', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'verified_at' => 'datetime',
        'submitted_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function claim()
    {
        return $this->belongsTo(PesantrenClaim::class, 'pesantren_claim_id');
    }

    public function user()
    {
        return $this->belongsTo(PesantrenProfile::class, 'user_id');
    }

    public function pricingPackage()
    {
        return $this->belongsTo(PricingPackage::class, 'pricing_package_id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class, 'payment_id');
    }
}
