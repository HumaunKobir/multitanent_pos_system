<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'email', 'phone', 'address',
        'payment_method_id', 'delivery_charge', 'subtotal', 'tailor_price', 'total',
        'payment_status', 'courier', 'status',
    ];

    protected $casts = [
        'delivery_charge' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tailor_price' => 'decimal:2',
        'total' => 'decimal:2',
        'status' => OrderStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(OnlineOrderProduct::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
