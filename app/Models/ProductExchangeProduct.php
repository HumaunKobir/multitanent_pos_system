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
        'return_quantity',
        'return_unit_price',
        'return_refund_amount',
        'return_batches',
        'new_product_id',
        'new_variation_id',
        'new_quantity',
        'new_unit_price',
        'new_line_discount',
        'new_promotion_id',
        'new_original_unit_price',
        'new_free_quantity',
        'new_promotion_discount',
        'new_promotion_meta',
        'new_batches',
    ];

    protected $casts = [
        'old_quantity' => 'decimal:2',
        'old_unit_price' => 'decimal:2',
        'old_batches' => 'array',
        'return_quantity' => 'decimal:2',
        'return_unit_price' => 'decimal:2',
        'return_refund_amount' => 'decimal:2',
        'return_batches' => 'array',
        'new_quantity' => 'decimal:2',
        'new_unit_price' => 'decimal:2',
        'new_line_discount' => 'decimal:2',
        'new_original_unit_price' => 'decimal:2',
        'new_free_quantity' => 'decimal:2',
        'new_promotion_discount' => 'decimal:2',
        'new_promotion_meta' => 'array',
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

    public function newPromotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'new_promotion_id');
    }
}
