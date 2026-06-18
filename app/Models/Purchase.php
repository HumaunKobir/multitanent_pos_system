<?php

namespace App\Models;

use App\Enums\PurchaseReceivedPayment;
use App\Enums\PurchaseType;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Purchase extends Model
{
    use HasBranchUser;

    protected $fillable = [
        'branch_id',
        'user_id',
        'supplier_id',
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

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVP'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function branch(): HasOne
    {
        return $this->hasOne(Branch::class, 'id', 'branch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
}
