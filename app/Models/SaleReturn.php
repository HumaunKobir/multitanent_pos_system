<?php

namespace App\Models;

use App\Enums\ReceivedPaymentMethod;
use App\Traits\HasBranchInvoiceNumber;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use HasBranchInvoiceNumber, HasBranchUser;

    protected $appends = ['invoice_number', 'net_amount'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'sell_id',
        'customer_id',
        'date',
        'gross_amount',
        'vat_amount',
        'vat_percent',
        'discount_amount',
        'invoice_discount_type',
        'invoice_discount_value',
        'round_off_amount',
        'paid_amount',
        'due_amount',
        'payment_type',
        'payment_account_id',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'invoice_discount_value' => 'decimal:2',
        'round_off_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'payment_type' => ReceivedPaymentMethod::class,
    ];

    public function getNetAmountAttribute(): float
    {
        return round(max(0, (float) $this->gross_amount + (float) $this->vat_amount - (float) $this->discount_amount), 2);
    }

    public function refundAmount(): float
    {
        return $this->net_amount;
    }

    public function dueAmount(): float
    {
        if ($this->due_amount !== null) {
            return round(max(0, (float) $this->due_amount), 2);
        }

        return round(max(0, $this->refundAmount() - (float) $this->paid_amount), 2);
    }

    public function isPartiallyRefunded(): bool
    {
        return (float) $this->paid_amount > 0 && $this->dueAmount() > 0;
    }

    public function isFullyRefunded(): bool
    {
        return $this->dueAmount() <= 0 && $this->refundAmount() > 0;
    }

    public function isEditable(): bool
    {
        return true;
    }

    public function isPaymentOnlyEditable(): bool
    {
        return false;
    }

    public function canAccessEdit(): bool
    {
        return true;
    }

    public function paymentStatusLabel(): string
    {
        if ($this->refundAmount() <= 0) {
            return 'settled';
        }

        if ($this->isFullyRefunded()) {
            return 'fully_refunded';
        }

        if ($this->isPartiallyRefunded()) {
            return 'partially_refunded';
        }

        return 'unpaid';
    }

    public static function invoicePrefix(): string
    {
        return 'INVSR';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sell(): BelongsTo
    {
        return $this->belongsTo(Sell::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SaleReturnProduct::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SaleReturnPayment::class);
    }
}
