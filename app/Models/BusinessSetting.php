<?php

namespace App\Models;

use App\Traits\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    use UsesCentralConnection;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            static::set($key, $value);
        }
    }
}
