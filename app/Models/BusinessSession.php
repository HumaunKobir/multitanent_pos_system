<?php

namespace App\Models;

use App\Enums\BusinessSessionOpeningMethod;
use App\Enums\BusinessSessionStatus;
use Database\Factories\BusinessSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessSession extends Model
{
    /** @use HasFactory<BusinessSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'session_number',
        'session_date',
        'branch_id',
        'started_by_user_id',
        'closed_by_user_id',
        'started_at',
        'closed_at',
        'opening_method',
        'status',
        'total_opening_balance',
        'total_closing_balance',
        'report_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_method' => BusinessSessionOpeningMethod::class,
            'status' => BusinessSessionStatus::class,
            'total_opening_balance' => 'decimal:2',
            'total_closing_balance' => 'decimal:2',
            'report_snapshot' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function accountBalances(): HasMany
    {
        return $this->hasMany(BusinessSessionAccountBalance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(BusinessSessionAuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
