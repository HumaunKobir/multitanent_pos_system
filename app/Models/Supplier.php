<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasBranch;

    protected $fillable = [
        'name',
        'phone',
        'company_name',
        'address',
        'branch_id',
        'status',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'status' => CommonStatus::class,
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
