<?php

namespace App\Models;

use App\Support\StorageUrl;
use App\Traits\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSecurityDeposit extends Model
{
    use HasFactory, UsesCentralConnection;

    protected $fillable = [
        'branch_id',
        'amount',
        'payment_method',
        'payment_account_id',
        'transaction_reference',
        'paid_at',
        'status',
        'notes',
        'recorded_by_user_id',
        'attachment_path',
    ];

    protected $appends = [
        'attachment_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? StorageUrl::public($this->attachment_path) : null;
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
