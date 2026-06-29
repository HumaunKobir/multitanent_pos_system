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

    protected $appends = [
        'invoice_number',
        'status_label',
    ];

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
        'return_sent_at',
        'return_sent_by_user_id',
        'return_received_at',
        'return_received_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => StockDistributionStatus::class,
        'received_at' => 'datetime',
        'return_sent_at' => 'datetime',
        'return_received_at' => 'datetime',
    ];

    public function getInvoiceNumberAttribute(): string
    {
        return 'INVT'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? StockDistributionStatus::Pending->label();
    }

    /**
     * Names of branches that have received any stock distributed from the given purchase.
     * While stock is held at a branch, the purchase cannot be returned from the admin
     * panel — the branch must first return the stock to the main warehouse.
     *
     * @return list<string>
     */
    public static function branchesHoldingReceivedStockForPurchase(int $purchaseId): array
    {
        return self::query()
            ->where('purchase_id', $purchaseId)
            ->where('status', '!=', StockDistributionStatus::Returned->value)
            ->whereHas('products', fn ($q) => $q->whereNotNull('received_at'))
            ->with('toBranch:id,name')
            ->get()
            ->map(fn (self $distribution): ?string => $distribution->toBranch?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isPending(): bool
    {
        return $this->status === StockDistributionStatus::Pending;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === StockDistributionStatus::PartiallyReceived;
    }

    public function isReceived(): bool
    {
        return $this->status === StockDistributionStatus::Received;
    }

    public function isReturnPending(): bool
    {
        return $this->status === StockDistributionStatus::ReturnPending;
    }

    public function isReturned(): bool
    {
        return $this->status === StockDistributionStatus::Returned;
    }

    public function isReceivable(): bool
    {
        return $this->isPending() || $this->isPartiallyReceived();
    }

    public function hasReceivedLines(): bool
    {
        return $this->products()->whereNotNull('received_at')->exists();
    }

    public function hasPendingLines(): bool
    {
        return $this->products()->whereNull('received_at')->exists();
    }

    public function syncStatusFromLines(): void
    {
        $this->loadMissing('products');

        $total = $this->products->count();
        $received = $this->products->filter(fn (StockDistributionProduct $line) => $line->isReceived())->count();

        if ($total === 0 || $received === 0) {
            $this->update([
                'status' => StockDistributionStatus::Pending,
                'received_at' => null,
                'received_by_user_id' => null,
            ]);

            return;
        }

        if ($received >= $total) {
            $lastReceived = $this->products
                ->filter(fn (StockDistributionProduct $line) => $line->isReceived())
                ->sortByDesc(fn (StockDistributionProduct $line) => $line->received_at)
                ->first();

            $this->update([
                'status' => StockDistributionStatus::Received,
                'received_at' => $lastReceived?->received_at,
                'received_by_user_id' => $lastReceived?->received_by_user_id,
            ]);

            return;
        }

        $this->update([
            'status' => StockDistributionStatus::PartiallyReceived,
            'received_at' => null,
            'received_by_user_id' => null,
        ]);
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

    public function returnSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_sent_by_user_id');
    }

    public function returnReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_received_by_user_id');
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
