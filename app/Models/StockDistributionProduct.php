<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDistributionProduct extends Model
{
    protected $fillable = [
        'branch_id',
        'stock_distribution_id',
        'product_id',
        'variation_id',
        'quantity',
        'main_stock_before',
        'source_batches',
        'destination_batches',
        'received_at',
        'received_by_user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'main_stock_before' => 'decimal:2',
        'source_batches' => 'array',
        'destination_batches' => 'array',
        'received_at' => 'datetime',
    ];

    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    public function stockDistribution(): BelongsTo
    {
        return $this->belongsTo(StockDistribution::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
