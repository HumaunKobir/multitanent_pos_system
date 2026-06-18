<?php

namespace App\Models;

use App\Enums\CommonStatus;
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
        'status' => CommonStatus::class,
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function mainWarehouseFor(int $variationId): ?self
    {
        $variation = static::query()->find($variationId);

        if (! $variation) {
            return null;
        }

        $mainBranchId = Branch::resolveMainBranchId();

        $mainVariation = static::query()
            ->where('product_id', $variation->product_id)
            ->where('sku', $variation->sku)
            ->where(function ($query) use ($mainBranchId) {
                $query->where('branch_id', $mainBranchId)
                    ->orWhereNull('branch_id');
            })
            ->first();

        if ($mainVariation) {
            return $mainVariation;
        }

        return static::query()->create([
            'product_id' => $variation->product_id,
            'branch_id' => $mainBranchId,
            'sku' => $variation->sku,
            'sku_code' => $variation->sku_code,
            'variation_data' => $variation->variation_data,
            'price' => $variation->price,
            'purchase_price' => $variation->purchase_price,
            'stock' => 0,
            'status' => $variation->status,
        ]);
    }
}
