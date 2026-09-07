<?php

namespace App\Models;

use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Database\Factories\CustomerCoinLotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCoinLot extends Model
{
    /** @use HasFactory<CustomerCoinLotFactory> */
    use HasBranch, HasFactory, UsesTenantConnection;

    protected $fillable = [
        'customer_id',
        'branch_id',
        'earn_transaction_id',
        'sell_id',
        'product_exchange_id',
        'original_coins',
        'remaining_coins',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'original_coins' => 'decimal:2',
            'remaining_coins' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function earnTransaction(): BelongsTo
    {
        return $this->belongsTo(CustomerCoinTransaction::class, 'earn_transaction_id');
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
