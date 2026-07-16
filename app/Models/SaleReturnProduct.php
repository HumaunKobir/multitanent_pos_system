<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnProduct extends Model
{
    protected $fillable = [
        'branch_id',
        'sale_return_id',
        'sell_product_id',
        'product_exchange_product_id',
        'product_id',
        'variation_id',
        'quantity',
        'unit_price',
        'batches',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'batches' => 'array',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function productExchangeProduct(): BelongsTo
    {
        return $this->belongsTo(ProductExchangeProduct::class);
    }

    public function isReplacementReturn(): bool
    {
        return $this->product_exchange_product_id !== null;
    }
}
