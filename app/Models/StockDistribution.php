<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVT'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(StockDistributionProduct::class);
    }
}
