<?php

namespace App\Models;

use App\Traits\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSubscriptionPayment extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'branch_id',
        'amount',
        'payment_method',
        'transaction_reference',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'paid_at',
        'recorded_by_user_id',
        'notes',
        'attachment_path',
    ];

    protected $appends = [
        'attachment_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billing_period_starts_at' => 'date',
            'billing_period_ends_at' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->attachment_path) : null;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
