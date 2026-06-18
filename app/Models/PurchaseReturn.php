<?php

namespace App\Models;

use App\Enums\PurchaseReceivedPayment;
use App\Traits\HasBranchUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use HasBranchUser;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'user_id',
        'purchase_id',
        'supplier_id',
        'date',
        'gross_amount',
        'paid_amount',
        'due_amount',
        'payment_type',
        'serial',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'payment_type' => PurchaseReceivedPayment::class,
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVPR'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
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
