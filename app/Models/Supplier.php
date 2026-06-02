<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Traits\HasAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasAccount;

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
        'status' => CommonStatus::class
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
