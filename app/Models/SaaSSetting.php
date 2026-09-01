<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaaSSetting extends Model
{
    protected $table = 'saas_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** Fetch setting value by key with fallback */
    public static function get(string $key, $default = null)
    {
        try {
            $setting = static::find($key);
            return $setting ? $setting->value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /** Set setting value by key */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }
}
