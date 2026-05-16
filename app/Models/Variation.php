<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variation extends Model
{
    protected $fillable = ['name', 'status'];

    public function values(): HasMany
    {
        return $this->hasMany(VariationValue::class);
    }
}
