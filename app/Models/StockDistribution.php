<?php

namespace App\Models;

use App\Enums\StockDistributionStatus;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDistribution extends Model
{
    use HasBranch;

    protected $appends = ['invoice_number'];

    protected $fillable = [
        'branch_id',
        'from_branch_id',
        'to_branch_id',
        'date',
        'serial',
        'comment',
        'status',
        'received_at',
        'received_by_user_id',
        'purchase_id',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => StockDistributionStatus::class,
        'received_at' => 'datetime',
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVT'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function isPending(): bool
    {
        return $this->status === StockDistributionStatus::Pending;
    }

    public function isReceived(): bool
    {
        return $this->status === StockDistributionStatus::Received;
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(StockDistributionProduct::class);
    }
}
