<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'key', 'value', 'description'];
    protected $casts = ['value' => 'json'];

    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
}
