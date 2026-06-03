<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductExchangeProduct extends Model
{
    protected $fillable = [
        'branch_id',
        'product_exchange_id',
        'sell_product_id',
        'old_product_id',
        'old_variation_id',
        'old_quantity',
        'old_unit_price',
        'old_batches',
        'new_product_id',
        'new_variation_id',
        'new_quantity',
        'new_unit_price',
        'new_batches',
    ];

    protected $casts = [
        'old_quantity' => 'decimal:2',
        'old_unit_price' => 'decimal:2',
        'old_batches' => 'array',
        'new_quantity' => 'decimal:2',
        'new_unit_price' => 'decimal:2',
        'new_batches' => 'array',
    ];

    public function productExchange(): BelongsTo
    {
        return $this->belongsTo(ProductExchange::class);
    }

    public function oldProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'old_product_id');
    }

    public function newProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'new_product_id');
    }

    public function oldVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'old_variation_id');
    }

    public function newVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'new_variation_id');
    }
}
