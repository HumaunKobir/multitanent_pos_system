<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\SaleType;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sell extends Model
{
    use HasBranchUser, HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'customer_id',
        'date',
        'gross_amount',
        'discount',
        'discount_type',
        'discount_value',
        'special_discount_id',
        'special_discount_amount',
        'vat',
        'paid_amount',
        'type',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'gross_amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'special_discount_amount' => 'decimal:2',
            'vat' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'type' => SaleType::class,
        ];
    }

    public function hasAnyDiscount(): bool
    {
        return $this->hasManualDiscount()
            || (float) $this->special_discount_amount > 0;
    }

    public function hasManualDiscount(): bool
    {
        return (float) $this->discount > 0
            || $this->lineDiscountTotal() > 0;
    }

    public function lineDiscountTotal(): float
    {
        if ($this->relationLoaded('products')) {
            return (float) $this->products->sum(fn (SellProduct $line) => (float) $line->discount);
        }

        return (float) $this->products()->sum('discount');
    }

    public function getNetAmountAttribute(): float
    {
        return (float) $this->gross_amount
            + (float) $this->vat
            - (float) $this->discount
            - (float) $this->special_discount_amount
            - $this->lineDiscountTotal();
    }

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVS'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function specialDiscount(): BelongsTo
    {
        return $this->belongsTo(SpecialDiscount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->user();
    }

    public function products(): HasMany
    {
        return $this->hasMany(SellProduct::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SellPayment::class);
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

    public function scopePaused(Builder $q): Builder
    {
        return $q->where('type', SaleType::Paused);
    }

    public function isPaused(): bool
    {
        return $this->type === SaleType::Paused;
    }
}
