<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineOrderProduct extends Model
{
    protected $fillable = [
        'online_order_id', 'product_id', 'variation_id', 'name', 'sku',
        'price', 'quantity', 'total_price', 'batches',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'batches' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'online_order_id');
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
