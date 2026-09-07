<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Database\Factories\SliderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    /** @use HasFactory<SliderFactory> */
    use HasFactory, UsesTenantConnection;

    protected $fillable = ['name', 'image', 'status'];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
