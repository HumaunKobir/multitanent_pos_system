<?php

namespace App\Models;

use App\Enums\SaleType;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sell extends Model
{
    use HasBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'date',
        'gross_amount',
        'discount',
        'vat',
        'paid_amount',
        'type',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'type' => SaleType::class,
    ];

    public function getNetAmountAttribute(): float
    {
        return (float) $this->gross_amount + (float) $this->vat - (float) $this->discount;
    }

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVS'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SellProduct::class);
    }

    public function saleReturns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function productExchange(): HasOne
    {
        return $this->hasOne(ProductExchange::class);
    }

    public function scopeSale(Builder $q): Builder
    {
        return $q->where('type', SaleType::Sale);
    }
}
