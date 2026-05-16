<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    protected $fillable = ['branch_id', 'name', 'phone', 'email', 'address'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'parti_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'parti_id');
    }
}
