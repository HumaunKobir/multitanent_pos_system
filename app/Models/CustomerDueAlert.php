<?php

namespace App\Models;

use App\Enums\CustomerDueAlertStatus;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDueAlert extends Model
{
    use HasBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_id',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
