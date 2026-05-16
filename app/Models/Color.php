<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = ['name', 'code', 'status'];

    public function scopeActive($query): Builder
    {
        return $query->where('status', 1);
    }
}
