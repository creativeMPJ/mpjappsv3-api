<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PesantrenDirectory extends Model
{
    protected $table     = 'pesantren_directory';
    protected $keyType   = 'string';
    use SoftDeletes;

    public $incrementing = false;

    protected $fillable = [
        'id', 'nama_pesantren', 'nama_pengasuh', 'alamat',
        'kota_kabupaten', 'regency_id', 'region_id',
        'no_wa_admin', 'email_admin', 'maps_link',
        'kode_regional', 'is_claimed', 'source_year',
    ];

    protected $casts = [
        'is_claimed' => 'boolean',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function regency()
    {
        return $this->belongsTo(Regency::class, 'regency_id');
    }
}
