<?php

namespace App\Models;

use App\Enums\PurchaseReceivedPayment;
use App\Enums\PurchaseType;
use App\Traits\HasBranchInvoiceNumber;
use App\Traits\HasBranchUser;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Purchase extends Model
{
    use HasBranchInvoiceNumber, HasBranchUser, HasFactory, UsesTenantConnection;

    protected $fillable = [
        'branch_id',
        'user_id',
        'supplier_id',
        'product_id',
        'parent_purchase_id',
        'date',
        'gross_amount',
        'discount',
        'vat',
        'paid_amount',
        'due_amount',
        'demarace',
        'purchase_type',
        'comment',
        'payment_type',
        'serial',
    ];

    protected $casts = [
        'purchase_type' => PurchaseType::class,
        'payment_type' => PurchaseReceivedPayment::class,
        'gross_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'demarace' => 'decimal:2',
        'date' => 'date',
    ];

    public function getNetAmountAttribute(): float
    {
        return (float) $this->gross_amount + (float) $this->vat - (float) $this->discount;
    }

    public static function invoicePrefix(): string
    {
        return 'INVP';
    }

    public function branch(): HasOne
    {
        return $this->hasOne(Branch::class, 'id', 'branch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseProducts(): HasMany
    {
        return $this->hasMany(PurchaseProduct::class, 'purchase_id');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function scopePurchase(Builder $q): Builder
    {
        return $q->where('purchase_type', PurchaseType::Purchase);
    }

    public function scopeInitialStock(Builder $q): Builder
    {
        return $q->where('purchase_type', PurchaseType::InitialStock);
    }

    public function scopeOpeningBalance(Builder $q): Builder
    {
        return $q->where('purchase_type', PurchaseType::OpeningBalance);
    }

    public function scopeSupplierPayable(Builder $q): Builder
    {
        return $q->whereIn('purchase_type', [
            PurchaseType::Purchase,
            PurchaseType::InitialStock,
            PurchaseType::OpeningBalance,
        ]);
    }

    /**
     * Regular purchases plus product initial-stock settlements (excludes opening balance).
     */
    public function scopePurchaseOrInitialStock(Builder $q): Builder
    {
        return $q->whereIn('purchase_type', [
            PurchaseType::Purchase,
            PurchaseType::InitialStock,
        ]);
    }

    /**
     * Purchase list uses the same branch scope as the purchase report.
     */
    public function scopeVisibleInBranchCatalog(Builder $query): Builder
    {
        return $query->ownBranch();
    }

    public function isAccessibleByCurrentUser(): bool
    {
        $user = Auth::user();

        if ($user?->isSuperAdmin()) {
            return true;
        }

        if ($user?->branch_id === null) {
            return false;
        }

        return (int) $this->branch_id === (int) $user->branch_id;
    }

    public function isMutableByCurrentUser(): bool
    {
        return $this->isAccessibleByCurrentUser();
    }
}
