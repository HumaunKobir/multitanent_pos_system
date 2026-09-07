<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentAllocation extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'customer_payment_id',
        'sell_id',
        'product_exchange_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    public function sell(): BelongsTo
    {
        return $this->belongsTo(Sell::class);
    }

    public function productExchange(): BelongsTo
    {
        return $this->belongsTo(ProductExchange::class);
    }

    /**
     * Stable identity for the settled document, unique across both types —
     * raw ids collide between a sale and an exchange.
     */
    public function documentKey(): string
    {
        return $this->product_exchange_id !== null
            ? 'exchange:'.$this->product_exchange_id
            : 'sale:'.$this->sell_id;
    }
}
