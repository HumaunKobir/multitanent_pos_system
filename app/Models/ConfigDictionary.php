<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class ConfigDictionary extends Model
{
    use UsesTenantConnection;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            static::set($key, $value);
        }
    }
}
