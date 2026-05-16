<?php

namespace App\Models;

use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    use HasAccount;

    protected $fillable = [
        'branch_id', 'number', 'name', 'account_info',
        'balance', 'balance_in', 'balance_out', 'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'balance_in' => 'decimal:2',
        'balance_out' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
