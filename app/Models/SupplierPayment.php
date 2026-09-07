<?php

namespace App\Models;

use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    use HasBranch, UsesTenantConnection;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'date',
        'amount',
        'serial',
        'comment',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return $this->serial ?? 'INVSP'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }
}
