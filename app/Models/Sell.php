<?php

namespace App\Models;

use App\Enums\SaleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sell extends Model
{
    protected $fillable = [
        'branch_id', 'customer_id', 'date',
        'gross_amount', 'discount', 'vat', 'paid_amount',
        'type', 'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'gross_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'type' => SaleType::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SellProduct::class);
    }
}
