<?php

namespace App\Models;

use App\Enums\ProductLogType;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $fillable = [
        'branch_id',
        'product_id',
        'supplier_id',
        'serial',
        'purchase_price',
        'available',
        'expiry_date',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'available' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeAtBranchWarehouse(Builder $query, ?int $branchId): Builder
    {
        if ($branchId === Branch::resolveMainBranchId()) {
            return $query->where(function (Builder $q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        if ($branchId === null) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    public function inStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Purchase, $quantity);
    }

    public function initialStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::InitialStock, $quantity);
    }

    public function damageStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Damage, $quantity);
    }

    public function purchaseReturnStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Purchase_Return, $quantity);
    }

    public function outStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Sale, $quantity);
    }

    public function saleReturnStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Sale_Return, $quantity);
    }

    public function exchangeStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Exchange, $quantity);
    }

    public function distributionOutStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Distribution_Out, $quantity);
    }

    public function distributionInStock(int|float $quantity): void
    {
        $this->stockLog(ProductLogType::Distribution_In, $quantity);
    }

    public function adjustmentStock(int|float $quantity): void
    {
        $this->stockLog(
            $quantity >= 0 ? ProductLogType::Adjustment_In : ProductLogType::Adjustment_Out,
            abs($quantity),
        );
    }

    private function stockLog(ProductLogType $type, int|float $quantity): void
    {
        ProductInOutLog::create([
            'batch_id' => $this->id,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'quantity' => $quantity,
            'type' => $type->value,
            'stock' => $this->available,
            'remark' => $type->name,
        ]);
    }
}
