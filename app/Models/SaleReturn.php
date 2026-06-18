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

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'sell_id',
        'customer_id',
        'date',
        'gross_amount',
        'paid_amount',
        'payment_type',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_type' => ReceivedPaymentMethod::class,
    ];

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

    public function products(): HasMany
    {
        return $this->hasMany(SaleReturnProduct::class);
    }
}
