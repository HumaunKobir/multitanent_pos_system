<?php

namespace App\Models;

use App\Traits\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionInvoice extends Model
{
    use HasFactory, UsesCentralConnection;

    protected $fillable = [
        'branch_id',
        'invoice_number',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'due_date',
        'subtotal',
        'discount',
        'total_amount',
        'paid_amount',
        'due_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_starts_at' => 'date',
            'billing_period_ends_at' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SubscriptionPaymentAllocation::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';
        $latest = static::query()
            ->where('invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('invoice_number');

        if ($latest) {
            $lastNum = (int) substr($latest, strlen($prefix));
            $nextNum = str_pad((string) ($lastNum + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $nextNum = '0001';
        }

        return $prefix.$nextNum;
    }
}
