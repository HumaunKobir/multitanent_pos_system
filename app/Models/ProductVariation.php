<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    protected $fillable = [
        'branch_id', 'product_id', 'sku', 'sku_code', 'variation_data',
        'price', 'purchase_price', 'stock', 'status',
    ];

    protected $casts = [
        'variation_data' => 'array',
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'status' => CommonStatus::class
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
