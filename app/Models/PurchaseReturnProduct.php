<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnProduct extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $fillable = [
        'branch_id',
        'purchase_return_id',
        'purchase_product_id',
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

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
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
