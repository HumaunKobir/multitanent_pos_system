<?php

namespace App\Models;

use App\Enums\ReceivedPaymentMethod;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use HasBranchUser;

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
        'paid_amount',
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
        'paid_amount' => 'decimal:2',
        'payment_type' => ReceivedPaymentMethod::class,
    ];

    public function getNetAmountAttribute(): float
    {
        return round(max(0, (float) $this->gross_amount + (float) $this->vat_amount - (float) $this->discount_amount), 2);
    }

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVSR'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
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
