<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellProduct extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'branch_id', 'sell_id', 'product_id', 'variation_id',
        'quantity', 'free_quantity', 'unit_price', 'original_unit_price', 'discount',
        'promotion_id', 'promotion_discount', 'promotion_meta', 'batches',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'free_quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'original_unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'promotion_meta' => 'array',
        'batches' => 'array',
    ];

    public function sell(): BelongsTo
    {
        return $this->belongsTo(Sell::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
