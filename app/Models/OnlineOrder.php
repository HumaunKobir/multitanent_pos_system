<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\SteadfastCourierException;
use App\Support\SteadfastPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineOrder extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'email', 'phone', 'address',
        'payment_method', 'transaction_id', 'delivery_charge', 'subtotal', 'total',
        'payment_status', 'courier', 'courier_invoice', 'courier_consignment_id',
        'courier_tracking_code', 'courier_status', 'courier_sent_at', 'status',
        'order_email_sent_at', 'payment_email_sent_at',
    ];

    protected $casts = [
        'delivery_charge' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'courier_sent_at' => 'datetime',
        'order_email_sent_at' => 'datetime',
        'payment_email_sent_at' => 'datetime',
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

    public function courierInvoice(): string
    {
        $prefix = (string) config('steadfast.invoice_prefix', 'ORD');

        return $prefix.'-'.$this->id;
    }

    public function invoiceNumber(): string
    {
        return 'INV-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function notificationEmail(): ?string
    {
        if (filled($this->email)) {
            return $this->email;
        }

        $this->loadMissing('customer');

        return filled($this->customer?->email) ? $this->customer->email : null;
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cod' => 'Cash on Delivery',
            'sslcommerz' => 'Online Payment (SSLCommerz)',
            default => ucfirst((string) $this->payment_method),
        };
    }

    public function hasSteadfastShipment(): bool
    {
        return $this->courier === 'steadfast'
            && filled($this->courier_consignment_id);
    }

    public function canSendToSteadfast(): bool
    {
        return $this->steadfastSendBlockReason() === null;
    }

    public function steadfastSendBlockReason(): ?string
    {
        if ($this->hasSteadfastShipment()) {
            return 'This order has already been sent to Steadfast.';
        }

        if (in_array($this->status, [OrderStatus::Delivered, OrderStatus::Canceled], true)) {
            return 'Delivered or cancelled orders cannot be sent to Steadfast.';
        }

        if (! filled($this->name) || ! filled($this->phone) || ! filled($this->address)) {
            return 'Recipient name, phone, and address are required.';
        }

        try {
            SteadfastPhone::normalize($this->phone);
        } catch (SteadfastCourierException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function steadfastCodAmount(): float
    {
        if ($this->payment_method === 'cod') {
            return (float) $this->total;
        }

        return 0.0;
    }

    public function steadfastDeliveryNote(): ?string
    {
        $parts = array_filter([
            $this->payment_method === 'cod' ? 'COD order' : 'Prepaid order',
            filled($this->transaction_id) ? 'Txn: '.$this->transaction_id : null,
        ]);

        return $parts === [] ? null : implode(' | ', $parts);
    }

    public function steadfastItemDescription(): ?string
    {
        $this->loadMissing('products');

        if ($this->products->isEmpty()) {
            return null;
        }

        return $this->products
            ->map(fn (OnlineOrderProduct $item): string => $item->name.' x'.$item->quantity)
            ->implode(', ');
    }

    /**
     * @param  Builder<OnlineOrder>  $query
     * @return Builder<OnlineOrder>
     */
    public function scopeAwaitingSteadfastStatusSync(Builder $query): Builder
    {
        return $query
            ->where('courier', 'steadfast')
            ->where('status', OrderStatus::Shipping)
            ->whereNotNull('courier_consignment_id')
            ->where(function (Builder $inner): void {
                $inner->whereNull('courier_status')
                    ->orWhereNotIn('courier_status', [
                        'delivered',
                        'partial_delivered',
                        'cancelled',
                    ]);
            });
    }
}
