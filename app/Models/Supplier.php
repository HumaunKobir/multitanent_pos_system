<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasBranch, HasFactory, UsesTenantConnection;

    protected $fillable = [
        'name',
        'phone',
        'company_name',
        'address',
        'branch_id',
        'status',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'status' => CommonStatus::class,
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function scopeAccessibleAtBranch(\Illuminate\Database\Eloquent\Builder $query, int $branchId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereNull('branch_id')
                ->orWhere('branch_id', Branch::resolveAdminCatalogBranchId());
        });
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
