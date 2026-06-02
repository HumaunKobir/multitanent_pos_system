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
        'branch_id',
        'name',
        'email',
        'phone',
        'address',
        'password',
        'balance',
        'status',
        'is_default',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    protected $casts = [
        'is_default' => 'boolean',
        'balance' => 'decimal:2',
        'balance_in' => 'decimal:2',
        'balance_out' => 'decimal:2',
    ];

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
