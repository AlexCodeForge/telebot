<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    /**
     * Get a setting value
     */
    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        // Cast value based on type
        switch ($setting->type) {
            case 'integer':
                return (int) $setting->value;
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
                return json_decode($setting->value, true);
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }

    /**
     * Set a setting value
     */
    public static function set($key, $value, $type = 'string')
    {
        // Convert value to string for storage
        $stringValue = $value;
        if (in_array($type, ['array', 'json'])) {
            $stringValue = json_encode($value);
        } elseif ($type === 'boolean') {
            $stringValue = $value ? '1' : '0';
        } else {
            $stringValue = (string) $value;
        }

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue, 'type' => $type]
        );
    }

    /**
     * Remove a setting
     */
    public static function forget($key)
    {
        return static::where('key', $key)->delete();
    }
}
