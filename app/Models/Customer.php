<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Traits\HasAccount;
use App\Traits\HasBranch;
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
        'member_ship_id',
        'name',
        'email',
        'phone',
        'address',
        'password',
        'status',
        'is_default',
        'is_membership',
        'point',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    protected $casts = [
        'status' => CommonStatus::class,
        'is_default' => 'boolean',
        'is_membership' => 'boolean',
        'balance' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberShipCard(): BelongsTo
    {
        return $this->belongsTo(MemberShipCard::class, 'member_ship_id');
    }

    public function sells(): HasMany
    {
        return $this->hasMany(Sell::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(OnlineOrder::class);
    }
}
