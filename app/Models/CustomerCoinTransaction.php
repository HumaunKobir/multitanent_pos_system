<?php

namespace App\Models;

use App\Enums\CoinTransactionType;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCoinTransaction extends Model
{
    use HasBranch;

    protected $fillable = [
        'customer_id',
        'branch_id',
        'sell_id',
        'product_exchange_id',
        'type',
        'coins',
        'balance_after',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => CoinTransactionType::class,
            'coins' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sell(): BelongsTo
    {
        return $this->belongsTo(Sell::class);
    }

    public function productExchange(): BelongsTo
    {
        return $this->belongsTo(ProductExchange::class);
    }
}
