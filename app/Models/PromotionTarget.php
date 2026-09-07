<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionTarget extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'promotion_id',
        'target_type',
        'target_id',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
