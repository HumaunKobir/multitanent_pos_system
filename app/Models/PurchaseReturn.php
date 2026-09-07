<?php

namespace App\Models;

use App\Enums\PurchaseReceivedPayment;
use App\Traits\HasBranchInvoiceNumber;
use App\Traits\HasBranchUser;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use HasBranchInvoiceNumber, HasBranchUser, HasFactory, UsesTenantConnection;

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
        'purchase_due_offset',
        'received_amount',
        'payment_type',
        'payment_account_id',
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
        'purchase_due_offset' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'payment_type' => PurchaseReceivedPayment::class,
    ];

    public static function invoicePrefix(): string
    {
        return 'INVPR';
    }

    public function getNetAmountAttribute(): float
    {
        return (float) $this->gross_amount + (float) $this->vat - (float) $this->discount;
    }

    public function getVatPercentAttribute(): float
    {
        $taxable = max(0, (float) $this->gross_amount - (float) $this->discount);

        return $taxable > 0 ? ((float) $this->vat / $taxable) * 100 : 0;
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

    public function payments(): HasMany
    {
        return $this->hasMany(PurchaseReturnPayment::class);
    }
}
