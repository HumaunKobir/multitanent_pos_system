<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageProduct extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'branch_id',
        'damage_id',
        'product_id',
        'variation_id',
        'quantity',
        'batches',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'batches' => 'array',
    ];

    public function damage(): BelongsTo
    {
        return $this->belongsTo(Damage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }
}
