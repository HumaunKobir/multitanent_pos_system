<?php

namespace App\Models;

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
        'name', 'email', 'phone', 'address', 'password',
        'branch_id', 'group_id', 'api_token',
        'balance', 'balance_in', 'balance_out',
        'status', 'is_default',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    protected $casts = [
        'is_default' => 'boolean',
        'balance' => 'decimal:2',
        'balance_in' => 'decimal:2',
        'balance_out' => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
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
}
