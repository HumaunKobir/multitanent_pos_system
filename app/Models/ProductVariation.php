<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id', 'branch_id', 'variation_data',
        'price', 'purchase_price', 'stock', 'status',
    ];

    protected $casts = [
        'variation_data' => 'array',
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
