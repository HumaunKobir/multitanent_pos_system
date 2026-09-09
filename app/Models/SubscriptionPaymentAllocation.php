<?php

namespace App\Models;

use App\Traits\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentAllocation extends Model
{
    use HasFactory, UsesCentralConnection;

    protected $fillable = [
        'branch_subscription_payment_id',
        'subscription_invoice_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BranchSubscriptionPayment::class, 'branch_subscription_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}
