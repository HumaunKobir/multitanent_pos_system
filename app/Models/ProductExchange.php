<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\ReceivedPaymentMethod;
use App\Traits\HasBranchInvoiceNumber;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductExchange extends Model
{
    use HasBranchInvoiceNumber, HasBranchUser;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'sell_id',
        'customer_id',
        'date',
        'gross_amount',
        'return_refund_amount',
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
        'due_amount',
        'price_difference',
        'payment_type',
        'payment_account_id',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'return_refund_amount' => 'decimal:2',
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
        'due_amount' => 'decimal:2',
        'price_difference' => 'decimal:2',
        'payment_type' => ReceivedPaymentMethod::class,
    ];

    public static function invoicePrefix(): string
    {
        return 'INVX';
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

    public function settlementAmount(): float
    {
        return round(max(
            abs((float) $this->price_difference),
            (float) $this->paid_amount + max(0, (float) $this->due_amount),
        ), 2);
    }

    public function dueAmount(): float
    {
        if ($this->due_amount !== null) {
            return round(max(0, (float) $this->due_amount), 2);
        }

        return round(max(0, $this->settlementAmount() - (float) $this->paid_amount), 2);
    }

    public function isPartiallyPaid(): bool
    {
        return (float) $this->paid_amount > 0 && $this->dueAmount() > 0;
    }

    public function isFullyPaid(): bool
    {
        return $this->dueAmount() <= 0 && $this->settlementAmount() > 0;
    }

    public function isEditable(): bool
    {
        if ($this->payment_type === ReceivedPaymentMethod::Customer_Account && $this->settlementAmount() > 0) {
            return false;
        }

        return ! $this->isPartiallyPaid() && ! $this->isFullyPaid();
    }

    public function isPaymentOnlyEditable(): bool
    {
        return $this->isPartiallyPaid();
    }

    public function canAccessEdit(): bool
    {
        return $this->isEditable() || $this->isPaymentOnlyEditable();
    }

    public function paymentStatusLabel(): string
    {
        if ($this->settlementAmount() <= 0) {
            return 'settled';
        }

        if ($this->isFullyPaid()) {
            return 'fully_paid';
        }

        if ($this->isPartiallyPaid()) {
            return 'partially_paid';
        }

        return 'unpaid';
    }
}
