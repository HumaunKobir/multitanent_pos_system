<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellPayment extends Model
{
    protected $fillable = [
        'sell_id',
        'payment_account_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function sell(): BelongsTo
    {
        return $this->belongsTo(Sell::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }
}
