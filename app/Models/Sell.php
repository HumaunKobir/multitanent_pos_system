<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sell extends Model
{
    protected $fillable = [
        'branch_id', 'customer_id', 'date',
        'gross_amount', 'discount', 'vat', 'paid_amount',
        'type', 'comment', 'parent_id', 'child_id',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Sell::class, 'parent_id');
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Sell::class, 'child_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(Sell::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SellProduct::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }
}
