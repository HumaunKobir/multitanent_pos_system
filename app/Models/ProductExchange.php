<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\ReceivedPaymentMethod;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductExchange extends Model
{
    use HasBranchUser;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'sell_id',
        'customer_id',
        'date',
        'gross_amount',
        'special_discount_id',
        'special_discount_amount',
        'promotion_discount_total',
        'discount',
        'discount_type',
        'discount_value',
        'vat',
        'round_off_amount',
        'coins_redeemed',
        'coin_discount_amount',
        'coins_earned',
        'net_amount',
        'paid_amount',
        'price_difference',
        'payment_type',
        'payment_account_id',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'special_discount_amount' => 'decimal:2',
        'promotion_discount_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_type' => DiscountType::class,
        'discount_value' => 'decimal:2',
        'vat' => 'decimal:2',
        'round_off_amount' => 'decimal:2',
        'coins_redeemed' => 'decimal:2',
        'coin_discount_amount' => 'decimal:2',
        'coins_earned' => 'decimal:2',
        'net_amount' => 'decimal:2',
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
        return (float) $this->gross_amount
            + (float) $this->vat
            - (float) $this->discount
            - (float) $this->special_discount_amount
            - (float) $this->coin_discount_amount
            - (float) $this->round_off_amount;
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductExchangeProduct::class);
    }
}
