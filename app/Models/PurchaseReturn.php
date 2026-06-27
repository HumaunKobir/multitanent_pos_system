<?php

namespace App\Models;

use App\Enums\PurchaseReceivedPayment;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use HasBranchUser;
    use HasFactory;

    protected $appends = ['invoice_number', 'net_amount', 'vat_percent'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'purchase_id',
        'supplier_id',
        'date',
        'gross_amount',
        'discount',
        'vat',
        'paid_amount',
        'due_amount',
        'payment_type',
        'serial',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'payment_type' => PurchaseReceivedPayment::class,
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVPR'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function getNetAmountAttribute(): float
    {
        return (float) $this->gross_amount + (float) $this->vat - (float) $this->discount;
    }

    public function getVatPercentAttribute(): float
    {
        $gross = (float) $this->gross_amount;

        return $gross > 0 ? ((float) $this->vat / $gross) * 100 : 0;
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(PurchaseReturnProduct::class);
    }
}
