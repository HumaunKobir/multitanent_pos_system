<?php

namespace App\Models;

use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasAccount;

    protected $fillable = [
        'name', 'phone', 'company_name', 'address',
        'branch_id', 'status',
        'balance', 'balance_in', 'balance_out',
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

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
