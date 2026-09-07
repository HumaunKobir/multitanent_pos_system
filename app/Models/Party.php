<?php

namespace App\Models;

use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasBranch, HasFactory, UsesTenantConnection;

    protected $fillable = ['branch_id', 'name', 'phone', 'email', 'address'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vouchers(): MorphMany
    {
        return $this->morphMany(Voucher::class, 'party');
    }
}
