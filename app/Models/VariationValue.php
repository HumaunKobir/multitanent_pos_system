<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariationValue extends Model
{
    use UsesTenantConnection;

    protected $fillable = ['variation_id', 'value', 'status'];

    protected $casts = [
        'status' => CommonStatus::class,
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }
}
