<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanCode extends Model
{
    protected $table = 'jabatan_codes';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id', 'name', 'code', 'description'];

    public function crews()
    {
        return $this->hasMany(Crew::class, 'jabatan_code_id');
    }
}
