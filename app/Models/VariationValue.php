<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariationValue extends Model
{
    protected $fillable = ['variation_id', 'value', 'status'];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }
}
