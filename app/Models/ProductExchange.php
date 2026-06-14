<?php

namespace App\Models;

use App\Enums\ReceivedPaymentMethod;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductExchange extends Model
{
    use HasBranch;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'sell_id',
        'customer_id',
        'date',
        'gross_amount',
        'special_discount_id',
        'special_discount_amount',
        'paid_amount',
        'price_difference',
        'payment_type',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'special_discount_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'price_difference' => 'decimal:2',
        'payment_type' => ReceivedPaymentMethod::class,
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVX'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function sell(): BelongsTo
    {
        return $this->belongsTo(Sell::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function specialDiscount(): BelongsTo
    {
        return $this->belongsTo(SpecialDiscount::class);
    }

    public function getNetNewAmountAttribute(): float
    {
        return (float) $this->gross_amount - (float) $this->special_discount_amount;
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductExchangeProduct::class);
    }
}
