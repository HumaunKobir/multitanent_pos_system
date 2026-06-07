<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellProduct extends Model
{
    protected $fillable = [
        'branch_id', 'sell_id', 'product_id', 'variation_id',
        'quantity', 'unit_price', 'discount', 'batches',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
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
}
