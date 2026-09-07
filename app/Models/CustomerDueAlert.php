<?php

namespace App\Models;

use App\Enums\CustomerDueAlertStatus;
use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDueAlert extends Model
{
    use HasBranch, HasFactory, UsesTenantConnection;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'sell_id',
        'due_given_date',
        'status',
    ];

    protected $casts = [
        'due_given_date' => 'date',
        'status' => CustomerDueAlertStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sell(): BelongsTo
    {
        return $this->belongsTo(Sell::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CustomerDueAlertStatus::Unpaid,
            CustomerDueAlertStatus::DateChanged,
        ]);
    }
}
