<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Enums\CustomerRegistrationType;
use App\Enums\OrderStatus;
use App\Support\StorageUrl;
use App\Traits\HasAccount;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasAccount, HasBranch, HasFactory, Notifiable;

    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'address',
        'image',
        'password',
        'status',
        'is_default',
        'point',
        'registration_type',
        'balance',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    protected $appends = ['image_url'];

    protected $casts = [
        'status' => CommonStatus::class,
        'registration_type' => CustomerRegistrationType::class,
        'is_default' => 'boolean',
        'balance' => 'decimal:2',
        'point' => 'decimal:2',
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => StorageUrl::public($this->image));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sells(): HasMany
    {
        return $this->hasMany(Sell::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(OnlineOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function hasReceivedProduct(Product $product): bool
    {
        return OnlineOrderProduct::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($query) => $query
                ->where('customer_id', $this->id)
                ->where('status', OrderStatus::Delivered))
            ->exists();
    }
}
