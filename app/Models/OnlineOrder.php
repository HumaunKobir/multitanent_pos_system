<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'email', 'phone', 'address',
        'payment_method', 'delivery_charge', 'subtotal', 'tailor_price', 'total',
        'payment_status', 'city_id', 'zone_id', 'area_id', 'courier', 'status',
    ];

    protected $casts = [
        'delivery_charge' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tailor_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(OnlineOrderProduct::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            1 => 'Pending',
            2 => 'Processing',
            3 => 'Shipping',
            5 => 'Delivered',
            6 => 'Canceled',
            default => 'Unknown',
        };
    }
}
